<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Services\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
