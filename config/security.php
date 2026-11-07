<?php

/*
|--------------------------------------------------------------------------
| Hardening login
|--------------------------------------------------------------------------
|
| Semua angka kebijakan keamanan login ada di sini supaya mudah dijelaskan
| di laporan dan mudah diubah tanpa menyentuh kode.
|
*/

return [

    // Blokir sementara setelah sekian kali salah password untuk kombinasi email + IP yang sama.
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_seconds' => (int) env('LOGIN_LOCKOUT_SECONDS', 300), // 5 menit

    // Batas total request login per IP per menit (menahan tebak password ke banyak email sekaligus).
    'login_ip_per_minute' => (int) env('LOGIN_IP_PER_MINUTE', 20),

    // Staf admin otomatis logout jika tidak ada aktivitas selama sekian menit.
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    // Kebijakan password untuk staf admin (saat ganti password).
    'staff_password_min' => 10,

];
