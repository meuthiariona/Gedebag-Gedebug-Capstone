<?php

/*
|--------------------------------------------------------------------------
| Backup & Restore
|--------------------------------------------------------------------------
|
| Kebijakan backup ditulis di sini supaya mudah dijelaskan di laporan
| (RPO = seberapa banyak data yang mungkin hilang, ditentukan oleh jadwal backup).
|
*/

return [

    // Folder penyimpanan backup (di disk "local" = storage/app/private).
    'directory' => 'backups',

    // Jadwal backup otomatis (format cron). Default: setiap hari pukul 01:00.
    // RPO maksimal = 24 jam data.
    'schedule' => env('BACKUP_SCHEDULE', '0 1 * * *'),

    // Retensi: jumlah backup otomatis terakhir yang disimpan. Yang lebih lama dihapus.
    'keep' => (int) env('BACKUP_KEEP', 7),

    // Tabel yang TIDAK ikut di-backup/restore (data sementara/teknis).
    'skip_tables' => ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'password_reset_tokens'],

    // Tabel yang ikut di-backup (arsip) tapi TIDAK ditimpa saat restore.
    // Audit log bersifat append-only, jadi restore tidak boleh menghapus jejak yang sudah ada.
    'preserve_on_restore' => ['activity_logs'],

    // Batas ukuran file backup yang boleh di-upload dari halaman admin (KB).
    'max_upload_kb' => 102400, // 100 MB

];
