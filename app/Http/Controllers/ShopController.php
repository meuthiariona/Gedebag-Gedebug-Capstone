<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\Product;
use App\Services\Availability;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function home()
    {
        $slides = HeroSlide::where('is_active', true)->orderBy('sort_order')->get();
        $categories = Category::active()
            ->with(['products' => fn ($q) => $q->where('is_active', true)])
            ->get()
            ->filter(fn ($c) => $c->products->isNotEmpty())
            ->each(fn ($c) => $c->products->each->setRelation('category', $c));
        $packages = Package::active()->orderByDesc('is_featured')->take(4)->get();

        return view('shop.home', compact('slides', 'categories', 'packages'));
    }

    public function products(Request $request)
    {
        $categories = Category::active()->get();
        $active = $request->query('category');
        $search = trim((string) $request->query('q'));

        $products = Product::active()->with('category')
            ->whereHas('category', fn ($q) => $q->where('is_active', true))
            ->when($active, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $active)))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%$search%")->orWhere('subtitle', 'like', "%$search%")))
            ->get();

        $packages = $search
            ? Package::active()->where(fn ($w) => $w->where('name', 'like', "%$search%")->orWhere('summary', 'like', "%$search%"))->get()
            : collect();

        $grouped = $categories->map(fn ($c) => ['category' => $c, 'products' => $products->where('category_id', $c->id)->values()])
            ->filter(fn ($g) => $g['products']->isNotEmpty());

        return view('shop.products', compact('categories', 'active', 'search', 'grouped', 'products', 'packages'));
    }

    public function availability(Request $request, Product $product)
    {
        abort_unless($product->is_active && $product->category?->is_active, 404);
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $firstMonth = today()->startOfMonth();
        $lastMonth = today()->addYears(2)->startOfMonth();
        $month = isset($filters['month']) ? Carbon::createFromFormat('!Y-m', $filters['month']) : $firstMonth;
        abort_unless($month->betweenIncluded($firstMonth, $lastMonth), 404);
        $days = Availability::productCalendar($product, $month, $month->copy()->endOfMonth());

        return view('shop.availability', compact('product', 'month', 'days', 'firstMonth', 'lastMonth'));
    }

    public function packages(Request $request)
    {
        $packageCategories = PackageCategory::orderBy('sort_order')->withCount(['packages' => fn ($q) => $q->where('is_active', true)])->get();
        $active = $request->query('type');

        $packages = Package::active()->with('category')
            ->when($active, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $active)))
            ->get()
            ->each(fn ($p) => $p->available = $p->stock); // ketersediaan per tanggal dicek saat checkout

        $total = Package::where('is_active', true)->count();

        return view('shop.packages', compact('packageCategories', 'packages', 'active', 'total'));
    }

    public function package(string $slug)
    {
        $package = Package::active()->where('slug', $slug)->with(['items', 'addons', 'category'])->firstOrFail();
        $package->available = $package->stock; // ketersediaan per tanggal dicek saat checkout
        $related = Package::active()->where('id', '!=', $package->id)->inRandomOrder()->take(3)->get();

        return view('shop.package', compact('package', 'related'));
    }
}
