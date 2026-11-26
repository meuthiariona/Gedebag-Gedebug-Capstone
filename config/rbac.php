<?php

/*
|--------------------------------------------------------------------------
| Role-Based Access Control (RBAC)
|--------------------------------------------------------------------------
|
| Satu-satunya tempat yang mendefinisikan peran dan izin. Middleware
| `permission:...`, Gate (@can di Blade) dan menu admin semuanya membaca
| dari file ini, jadi mengubah hak akses cukup di sini.
|
| Prinsip: least privilege — tiap peran hanya diberi izin yang dibutuhkan
| untuk pekerjaannya. Owner memakai '*' (semua izin).
|
*/

return [

    // Daftar izin yang dikenal aplikasi (dipakai untuk validasi & dokumentasi).
    'permissions' => [
        'admin.access' => 'Masuk ke panel admin & melihat dashboard',
        'bookings.view' => 'Melihat daftar & detail booking',
        'bookings.manage' => 'Mengubah status, pipeline, logistik & item booking',
        'bookings.negotiate' => 'ACC atau tolak harga hasil negosiasi',
        'bookings.delete' => 'Menghapus booking secara permanen',
        'finance.manage' => 'Mencatat/verifikasi/hapus pembayaran, mengatur diskon & DP',
        'catalog.manage' => 'Mengelola produk, talent, paket, kategori & banner',
        'users.manage' => 'Mengelola akun pengguna & perannya',
        'settings.manage' => 'Mengubah pengaturan (rekening bank, QRIS, kontak, dll.)',
        'audit.view' => 'Melihat & mengunduh audit log (jejak aktivitas)',
        'backup.manage' => 'Membuat, mengunduh, dan me-restore backup',
    ],

    // Peran => label + izin.
    'roles' => [
        'owner' => [
            'label' => 'Owner',
            'permissions' => ['*'],
        ],
        'finance' => [
            'label' => 'Admin Keuangan',
            'permissions' => ['admin.access', 'bookings.view', 'finance.manage'],
        ],
        'operations' => [
            'label' => 'Admin Operasional',
            'permissions' => ['admin.access', 'bookings.view', 'bookings.manage', 'catalog.manage'],
        ],
        'customer' => [
            'label' => 'Customer',
            'permissions' => [],
        ],
    ],

];
