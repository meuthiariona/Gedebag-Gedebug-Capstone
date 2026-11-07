<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---------- Backup & restore (lihat config/backup.php) ----------

Artisan::command('backup:run {--type=manual : manual | auto}', function (App\Services\BackupService $backup) {
    $b = $backup->create($this->option('type') === 'auto' ? 'auto' : 'manual');
    $this->info("Backup dibuat: {$b['name']} ({$b['rows']} baris data, {$b['uploads']} file upload, ".number_format($b['size'] / 1024, 1).' KB)');
})->purpose('Buat backup database + file upload');

Artisan::command('backup:list', function (App\Services\BackupService $backup) {
    $this->table(['File', 'Jenis', 'Waktu', 'Baris', 'Upload', 'Ukuran'], collect($backup->all())->map(fn ($b) => [
        $b['name'], App\Services\BackupService::TYPES[$b['type']] ?? $b['type'],
        Illuminate\Support\Carbon::parse($b['created_at'])->format('Y-m-d H:i'), $b['rows'], $b['uploads'],
        number_format($b['size'] / 1024, 1).' KB',
    ]));
})->purpose('Tampilkan daftar backup');

Artisan::command('backup:restore {file : nama file backup (lihat backup:list)} {--force : lewati konfirmasi}', function (App\Services\BackupService $backup) {
    $file = $this->argument('file');
    if (! $this->option('force') && ! $this->confirm("Semua data saat ini akan DIGANTI dengan isi {$file}. Lanjutkan?")) {
        return $this->warn('Dibatalkan.');
    }
    $r = $backup->restore($file);
    $this->info('Restore selesai: '.array_sum($r['tables']).' baris data, '.$r['uploads'].' file upload.');
    $this->line("Backup pengaman sebelum restore: {$r['safety_backup']}");
})->purpose('Pulihkan database + file upload dari backup');

// Backup otomatis terjadwal. Jalankan scheduler dengan: php artisan schedule:work
Illuminate\Support\Facades\Schedule::command('backup:run --type=auto')
    ->cron(config('backup.schedule'))
    ->timezone(env('BACKUP_TIMEZONE', 'Asia/Jakarta'))
    ->withoutOverlapping();
