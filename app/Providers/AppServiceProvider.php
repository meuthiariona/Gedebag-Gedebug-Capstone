<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('en');
        Paginator::useTailwind();

        // RBAC: setiap izin di config/rbac.php otomatis bisa dipakai lewat Gate / @can di Blade.
        Gate::before(function ($user, string $ability) {
            if (array_key_exists($ability, config('rbac.permissions', []))) {
                return $user->hasPermission($ability);
            }
        });

        // Hardening: batas request login per IP per menit.
        RateLimiter::for('login-ip', fn ($request) => Limit::perMinute(config('security.login_ip_per_minute'))->by($request->ip()));

        // Audit trail: catat login, login gagal & logout (berguna mendeteksi percobaan tebak password).
        Event::listen(Login::class, fn (Login $e) => ActivityLog::record('login', 'Login berhasil: '.$e->user->email, null, null, null, $e->user));
        Event::listen(Logout::class, fn (Logout $e) => $e->user ? ActivityLog::record('logout', 'Logout: '.$e->user->email, null, null, null, $e->user) : null);
        Event::listen(Lockout::class, fn (Lockout $e) => ActivityLog::record('login_locked', 'Login diblokir sementara (terlalu banyak percobaan) untuk email: '.$e->request->input('email', '-'), null, null, null, null));
        Event::listen(Failed::class, fn (Failed $e) => ActivityLog::record('login_failed', 'Login gagal untuk email: '.($e->credentials['email'] ?? '-'), null, null, null, null));

        // Data global untuk layout publik: jumlah keranjang & item wishlist milik user.
        View::composer('layouts.app', function ($view) {
            $view->with('cartCount', Cart::count());
            $view->with('footerCategories', once(fn () => Category::active()->take(4)->get()));
        });

        View::composer(['partials.ui', 'shop.package'], function ($view) {
            $view->with('wishKeys', once(fn () => auth()->check()
                ? auth()->user()->wishlists()->get()->map(fn ($w) => ($w->wishable_type === Product::class ? 'product-' : 'package-').$w->wishable_id)->all()
                : []));
        });
    }
}
