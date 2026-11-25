<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;

// ---------- Publik ----------
Route::get('/', [ShopController::class, 'home'])->name('home');
Route::get('/products', [ShopController::class, 'products'])->name('products');
Route::get('/packages', [ShopController::class, 'packages'])->name('packages');
Route::get('/packages/{slug}', [ShopController::class, 'package'])->name('packages.show');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/product/{product}', [CartController::class, 'addProduct'])->name('cart.product');
Route::post('/cart/package/{package}', [CartController::class, 'addPackage'])->name('cart.package');
Route::patch('/cart/{key}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{key}', [CartController::class, 'remove'])->name('cart.remove');

// Checkout tanpa login → ringkasan pesanan → negosiasi via WhatsApp
Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
Route::get('/order/{token}', [BookingController::class, 'summary'])->name('orders.summary');
Route::get('/order/{token}/whatsapp', [BookingController::class, 'whatsapp'])->name('orders.whatsapp');

// My Bookings: akun yang login + pesanan tamu yang tersimpan di browser ini
Route::get('/my-bookings', [BookingController::class, 'index'])->name('bookings.index');
Route::get('/my-bookings/{booking:code}', [BookingController::class, 'show'])->name('bookings.show');
Route::get('/my-bookings/{booking:code}/invoice', [BookingController::class, 'invoice'])->name('bookings.invoice');

// ---------- Auth ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login-ip'); // batas per IP, lihat AppServiceProvider
    Route::get('/register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ---------- Customer ----------
Route::middleware('auth')->group(function () {
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist');
    Route::post('/wishlist/{type}/{id}', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
});

// ---------- Admin (RBAC: izin per peran ada di config/rbac.php) ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin', 'staff.session'])->group(function () {
    Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

    // Ganti password (semua staf). Tetap bisa dibuka walau akun wajib ganti password.
    Route::get('/password', [Admin\PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [Admin\PasswordController::class, 'update'])->name('password.update');

    // Owner saja: pengaturan (rekening bank, QRIS, kontak) & manajemen pengguna
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/settings', [Admin\DashboardController::class, 'settings'])->name('settings');
        Route::post('/settings', [Admin\DashboardController::class, 'saveSettings']);
    });
    Route::resource('users', Admin\UserController::class)->except('show')->middleware('permission:users.manage');

    // Backup & restore: Owner saja
    Route::middleware('permission:backup.manage')->prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [Admin\BackupController::class, 'index'])->name('index');
        Route::post('/', [Admin\BackupController::class, 'store'])->name('store');
        Route::post('/upload', [Admin\BackupController::class, 'upload'])->name('upload');
        Route::get('/{name}/download', [Admin\BackupController::class, 'download'])->name('download');
        Route::post('/{name}/restore', [Admin\BackupController::class, 'restore'])->name('restore');
        Route::delete('/{name}', [Admin\BackupController::class, 'destroy'])->name('destroy');
    });

    // Audit log: Owner saja, hanya baca
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('activity-logs', [Admin\ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('activity-logs/export', [Admin\ActivityLogController::class, 'export'])->name('activity-logs.export');
    });

    // Katalog: Owner & Admin Operasional
    Route::middleware('permission:catalog.manage')->group(function () {
        Route::resource('categories', Admin\CategoryController::class)->except('show');
        Route::resource('products', Admin\ProductController::class)->except('show');
        Route::resource('package-categories', Admin\PackageCategoryController::class)->except('show');
        Route::resource('packages', Admin\PackageController::class)->except('show');
        Route::resource('hero-slides', Admin\HeroSlideController::class)->except('show');

        Route::post('packages/{package}/items', [Admin\PackageController::class, 'storeItem'])->name('packages.items.store');
        Route::delete('package-items/{item}', [Admin\PackageController::class, 'destroyItem'])->name('packages.items.destroy');
        Route::post('packages/{package}/addons', [Admin\PackageController::class, 'storeAddon'])->name('packages.addons.store');
        Route::delete('package-addons/{addon}', [Admin\PackageController::class, 'destroyAddon'])->name('packages.addons.destroy');
    });

    // Booking: semua staf boleh melihat
    Route::middleware('permission:bookings.view')->group(function () {
        Route::get('bookings', [Admin\BookingController::class, 'index'])->name('bookings.index');
        // Kalender booking + event Google Calendar (harus sebelum bookings/{booking})
        Route::get('bookings/calendar', [Admin\CalendarController::class, 'index'])->name('bookings.calendar');
        Route::get('bookings/calendar/events', [Admin\CalendarController::class, 'events'])->name('bookings.calendar.events');
        Route::get('bookings/{booking}', [Admin\BookingController::class, 'show'])->name('bookings.show');
    });
    // Ubah detail booking: Operasional (status, logistik) & Keuangan (diskon, DP) — field dibatasi di controller
    Route::put('bookings/{booking}', [Admin\BookingController::class, 'update'])->name('bookings.update')
        ->middleware('permission:bookings.manage|finance.manage');
    Route::middleware('permission:bookings.manage')->group(function () {
        Route::post('bookings/{booking}/items', [Admin\BookingController::class, 'storeItem'])->name('bookings.items.store');
        Route::delete('booking-items/{item}', [Admin\BookingController::class, 'destroyItem'])->name('bookings.items.destroy');
        Route::post('bookings/calendar/sync', [Admin\CalendarController::class, 'sync'])->name('bookings.calendar.sync');
    });
    Route::delete('bookings/{booking}', [Admin\BookingController::class, 'destroy'])->name('bookings.destroy')
        ->middleware('permission:bookings.delete');

    // Keuangan: Owner & Admin Keuangan
    Route::middleware('permission:finance.manage')->group(function () {
        Route::post('bookings/{booking}/payments', [Admin\BookingController::class, 'storePayment'])->name('bookings.payments.store');
        Route::post('payments/{payment}/verify', [Admin\BookingController::class, 'verifyPayment'])->name('payments.verify');
        Route::delete('payments/{payment}', [Admin\BookingController::class, 'destroyPayment'])->name('payments.destroy');
    });
});
