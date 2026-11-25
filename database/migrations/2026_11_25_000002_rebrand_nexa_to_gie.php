<?php

use App\Models\ActivityLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti nama brand contoh "NEXA" menjadi "GIE Entertainment" pada data yang SUDAH ada di database
 * (pengaturan, paket, produk, akun, booking), dan email akun contoh menjadi @google.com (dummy).
 * Seeder sudah diubah untuk instalasi baru; migrasi ini untuk server yang datanya sudah terisi.
 *
 * Tabel activity_logs sengaja tidak disentuh: audit trail bersifat append-only.
 */
return new class extends Migration
{
    /** Urutan penting: frasa panjang dulu, baru kata "NEXA" saja. */
    private const TEXT = [
        'PT NEXA Live Production & Equipment Ltd.' => 'PT GIE Entertainment',
        'NEXA Live Production & Equipment Ltd.' => 'PT GIE Entertainment',
        'PT NEXA Live Production' => 'PT GIE Entertainment',
        'NEXA Live Production' => 'GIE Entertainment',
        'NEXA Event Production' => 'GIE Entertainment',
        'Owner NEXA' => 'Owner GIE',
        'NEXA' => 'GIE Entertainment',
    ];

    private const EMAIL = [
        'ops@nexaevents.io' => 'ops@google.com',
        '@nexa.test' => '@google.com',
        'customer@example.com' => 'customer@google.com',
    ];

    private const TABLES = ['settings', 'users', 'bookings', 'booking_items', 'booking_logs', 'payments', 'products',
        'categories', 'packages', 'package_categories', 'package_items', 'package_addons', 'hero_slides'];

    public function up(): void
    {
        $changed = 0;
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = collect(Schema::getColumns($table))
                ->filter(fn ($c) => preg_match('/char|text|string/i', $c['type_name'] ?? $c['type']))
                ->pluck('name')->all();
            if (! $columns) {
                continue;
            }

            $rows = DB::table($table)->where(function ($q) use ($columns) {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'like', '%NEXA%')->orWhere($col, 'like', '%nexa%')->orWhere($col, 'like', '%customer@example.com%');
                }
            })->get();

            foreach ($rows as $row) {
                $update = [];
                foreach ($columns as $col) {
                    $value = $row->{$col};
                    if (! is_string($value) || $value === '') {
                        continue;
                    }
                    $new = str_replace(array_keys(self::EMAIL), array_values(self::EMAIL), $value);
                    $new = str_replace(array_keys(self::TEXT), array_values(self::TEXT), $new);
                    if ($new !== $value) {
                        $update[$col] = $new;
                    }
                }
                // Jangan bentrok dengan email yang sudah dipakai akun lain
                if ($table === 'users' && isset($update['email']) && DB::table('users')->where('email', $update['email'])->exists()) {
                    unset($update['email']);
                }
                if ($update) {
                    DB::table($table)->where('id', $row->id)->update($update);
                    $changed++;
                }
            }
        }

        Cache::forget('settings');
        if ($changed) {
            ActivityLog::record('updated', "Rebranding data: NEXA → GIE Entertainment, email contoh → @google.com ({$changed} baris)");
        }
    }

    public function down(): void
    {
        // Perubahan data (rebranding) tidak dibalik otomatis.
    }
};
