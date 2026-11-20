<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Backup & restore aplikasi dalam satu file .zip berisi:
 *   - manifest.json : info backup + checksum SHA-256 (cek integritas)
 *   - database.json : isi semua tabel penting (format netral → jalan di SQLite maupun MySQL/XAMPP,
 *                     tanpa butuh mysqldump)
 *   - uploads/...   : foto & file yang di-upload dari admin (storage/app/public)
 */
class BackupService
{
    /** Pola nama file yang sah. Mencegah path traversal (mis. "../../.env"). */
    public const NAME_PATTERN = '/^gie-backup-(auto|manual|pre-restore|upload)-\d{8}-\d{6}(-\d+)?\.zip$/';

    public const TYPES = [
        'auto' => 'Otomatis (terjadwal)',
        'manual' => 'Manual',
        'pre-restore' => 'Pengaman sebelum restore',
        'upload' => 'Upload dari luar',
    ];

    // ------------------------------------------------------------------ backup

    public function create(string $type = 'manual'): array
    {
        $this->ensureZip();
        $name = $this->uniqueName($type);
        $path = $this->disk()->path($this->dir().'/'.$name);
        $this->disk()->makeDirectory($this->dir());

        $data = [];
        foreach ($this->backupTables() as $table) {
            $data[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Gagal membuat file backup.');
        }
        $zip->addFromString('database.json', $json);

        $uploads = 0;
        foreach (Storage::disk('public')->allFiles() as $file) {
            if (basename($file) === '.gitignore') {
                continue;
            }
            $zip->addFile(Storage::disk('public')->path($file), 'uploads/'.$file);
            $uploads++;
        }

        $manifest = [
            'app' => config('app.name'),
            'type' => $type,
            'created_at' => now()->toIso8601String(),
            'database_driver' => DB::getDriverName(),
            'tables' => array_map('count', $data),
            'uploads' => $uploads,
            'database_sha256' => hash('sha256', $json),
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $zip->close();

        ActivityLog::record('backup_created', "Backup dibuat: {$name}", null, null, [
            'file' => $name, 'type' => $type, 'rows' => array_sum($manifest['tables']), 'uploads' => $uploads,
        ]);

        if ($type === 'auto') {
            $this->applyRetention();
        }

        return $this->info($name);
    }

    /** Daftar backup, terbaru dulu. */
    public function all(): array
    {
        return collect($this->disk()->files($this->dir()))
            ->map(fn ($f) => basename($f))
            ->filter(fn ($n) => preg_match(self::NAME_PATTERN, $n))
            ->map(fn ($n) => $this->info($n))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function info(string $name): array
    {
        $path = $this->pathFor($name);
        $manifest = $this->readManifest($path);

        return [
            'name' => $name,
            'type' => preg_match(self::NAME_PATTERN, $name, $m) ? $m[1] : ($manifest['type'] ?? 'manual'),
            'size' => filesize($path),
            'created_at' => $manifest['created_at'] ?? date('c', filemtime($path)),
            'rows' => array_sum($manifest['tables'] ?? []),
            'tables' => count($manifest['tables'] ?? []),
            'uploads' => $manifest['uploads'] ?? 0,
            'driver' => $manifest['database_driver'] ?? '-',
        ];
    }

    public function pathFor(string $name): string
    {
        if (! preg_match(self::NAME_PATTERN, $name) || ! $this->disk()->exists($this->dir().'/'.$name)) {
            throw new RuntimeException('File backup tidak ditemukan.');
        }

        return $this->disk()->path($this->dir().'/'.$name);
    }

    public function delete(string $name): void
    {
        $this->pathFor($name);
        $this->disk()->delete($this->dir().'/'.$name);
        ActivityLog::record('backup_deleted', "Backup dihapus: {$name}");
    }

    /** Simpan file backup hasil upload (mis. dari laptop/flashdisk lain) setelah dicek keasliannya. */
    public function import(string $uploadedPath): array
    {
        $this->verify($uploadedPath);
        $name = $this->uniqueName('upload');
        $this->disk()->makeDirectory($this->dir());
        copy($uploadedPath, $this->disk()->path($this->dir().'/'.$name));
        ActivityLog::record('backup_uploaded', "Backup di-upload: {$name}");

        return $this->info($name);
    }

    // ----------------------------------------------------------------- restore

    /**
     * Pulihkan database & file upload dari backup.
     * Langkah aman: verifikasi checksum → buat backup pengaman → ganti data dalam satu transaksi.
     */
    public function restore(string $name): array
    {
        $path = $this->pathFor($name);
        $this->verify($path);

        $actorId = auth()->id();
        $actorName = auth()->user()?->name ?? 'Sistem (terminal)';

        // 1. Backup pengaman: kalau restore ternyata salah pilih file, kondisi sebelumnya masih bisa dikembalikan.
        $safety = $this->create('pre-restore');

        // 2. Baca isi backup
        $zip = new ZipArchive;
        $zip->open($path);
        $data = json_decode($zip->getFromName('database.json'), true, 512, JSON_THROW_ON_ERROR);

        // 3. Ganti isi database dalam satu transaksi (kalau gagal di tengah, semua dibatalkan)
        // Urutkan tabel induk dulu (users, bookings) baru anaknya (payments, booking_items) agar relasi tetap sah.
        $restorable = $this->sortByDependency(array_diff($this->backupTables(), config('backup.preserve_on_restore')));
        $restored = [];
        Schema::disableForeignKeyConstraints();
        try {
            DB::transaction(function () use ($restorable, $data, &$restored) {
                foreach (array_reverse($restorable) as $table) {
                    DB::table($table)->delete();
                }
                foreach ($restorable as $table) {
                    $rows = $data[$table] ?? [];
                    // Hanya kolom yang ada di skema sekarang (aman jika ada migrasi baru setelah backup dibuat)
                    $columns = array_flip(Schema::getColumnListing($table));
                    $rows = array_map(fn ($r) => array_intersect_key($r, $columns), $rows);
                    foreach (array_chunk($rows, 200) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                    $restored[$table] = count($rows);
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        // 4. Pulihkan file upload
        $files = $this->restoreUploads($zip);
        $zip->close();
        Cache::forget('settings');

        // 5. Catat di audit log. Akun pelaku mungkin tidak ada di data hasil restore.
        $actor = $actorId ? User::find($actorId) : null;
        if ($actorId && ! $actor && auth()->hasUser()) {
            auth()->logout();
        }
        ActivityLog::record('backup_restored', "Restore dari {$name} oleh {$actorName}", null, null, [
            'file' => $name, 'safety_backup' => $safety['name'], 'rows' => array_sum($restored), 'uploads' => $files,
        ], $actor);

        return ['tables' => $restored, 'uploads' => $files, 'safety_backup' => $safety['name']];
    }

    /** Cek file backup utuh & asli (struktur benar, checksum cocok). */
    public function verify(string $path): array
    {
        $this->ensureZip();
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('File bukan arsip backup yang valid.');
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $json = $zip->getFromName('database.json');
        $zip->close();

        if (! is_array($manifest) || $json === false || empty($manifest['database_sha256'])) {
            throw new RuntimeException('File backup tidak lengkap (manifest atau database hilang).');
        }
        if (! hash_equals($manifest['database_sha256'], hash('sha256', $json))) {
            throw new RuntimeException('Checksum tidak cocok: file backup rusak atau sudah diubah. Restore dibatalkan.');
        }

        return $manifest;
    }

    // ----------------------------------------------------------------- helpers

    private function restoreUploads(ZipArchive $zip): int
    {
        $public = Storage::disk('public');
        foreach ($public->allFiles() as $file) {
            if (basename($file) !== '.gitignore') {
                $public->delete($file);
            }
        }

        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (! str_starts_with($entry, 'uploads/') || str_ends_with($entry, '/')) {
                continue;
            }
            $relative = substr($entry, strlen('uploads/'));
            if (str_contains($relative, '..') || str_starts_with($relative, '/')) {
                continue; // tolak entri berbahaya (zip slip)
            }
            $public->put($relative, $zip->getFromIndex($i));
            $count++;
        }

        return $count;
    }

    private function applyRetention(): void
    {
        collect($this->all())
            ->where('type', 'auto')
            ->slice(config('backup.keep'))
            ->each(fn ($b) => $this->disk()->delete($this->dir().'/'.$b['name']));
    }

    private function backupTables(): array
    {
        // Hanya tabel di database aplikasi ini (di MySQL/XAMPP, tanpa filter ini tabel database lain ikut terbaca).
        return collect(Schema::getTables(Schema::getCurrentSchemaListing()))
            ->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_') || in_array($t, config('backup.skip_tables'), true))
            ->values()
            ->all();
    }

    /** Urutan topologis berdasarkan foreign key: tabel yang dirujuk berada lebih dulu. */
    private function sortByDependency(array $tables): array
    {
        $deps = [];
        foreach ($tables as $t) {
            $deps[$t] = collect(Schema::getForeignKeys($t))->pluck('foreign_table')
                ->filter(fn ($f) => $f !== $t && in_array($f, $tables, true))->unique()->values()->all();
        }

        $sorted = [];
        $visit = function ($t, array $stack = []) use (&$visit, &$sorted, $deps) {
            if (in_array($t, $sorted, true) || in_array($t, $stack, true)) {
                return;
            }
            foreach ($deps[$t] as $d) {
                $visit($d, [...$stack, $t]);
            }
            $sorted[] = $t;
        };
        foreach ($tables as $t) {
            $visit($t);
        }

        return $sorted;
    }

    private function readManifest(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return [];
        }
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true) ?: [];
        $zip->close();

        return $manifest;
    }

    private function uniqueName(string $type): string
    {
        $base = 'gie-backup-'.$type.'-'.now()->format('Ymd-His');
        $name = $base.'.zip';
        for ($i = 2; $this->disk()->exists($this->dir().'/'.$name); $i++) {
            $name = "{$base}-{$i}.zip";
        }

        return $name;
    }

    private function ensureZip(): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Ekstensi PHP "zip" belum aktif. Aktifkan extension=zip di php.ini.');
        }
    }

    private function disk()
    {
        return Storage::disk('local');
    }

    private function dir(): string
    {
        return config('backup.directory');
    }
}
