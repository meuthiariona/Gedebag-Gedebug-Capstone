<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\HeroSlide;
use App\Models\Package;
use App\Models\PackageCategory;
use App\Models\Product;
use App\Services\Availability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    public function packages(Request $request)
    {
        $packageCategories = PackageCategory::orderBy('sort_order')->withCount(['packages' => fn ($q) => $q->where('is_active', true)])->get();
        $active = $request->query('type');

        $packages = Package::active()->with('category')
            ->when($active, fn ($q) => $q->whereHas('category', fn ($c) => $c->where('slug', $active)))
            ->get()
            ->each(fn ($p) => $p->available = Availability::package($p));

        $total = Package::where('is_active', true)->count();

        return view('shop.packages', compact('packageCategories', 'packages', 'active', 'total'));
    }

    public function package(string $slug)
    {
        $package = Package::active()->where('slug', $slug)->with(['items', 'addons', 'category'])->firstOrFail();
        $package->available = Availability::package($package);
        $related = Package::active()->where('id', '!=', $package->id)->inRandomOrder()->take(3)->get();

        return view('shop.package', compact('package', 'related'));
    }

    /** "Event Availability Engine" — simpan tanggal sewa yang dipilih ke session. */
    public function setDates(Request $request)
    {
        $data = $request->validate([
            'start' => ['required', 'date', 'after_or_equal:today'],
            'end' => ['required', 'date', 'after_or_equal:start'],
        ], [], ['start' => 'tanggal mulai', 'end' => 'tanggal selesai']);

        session([
            'rental_start' => Carbon::parse($data['start'])->toDateString(),
            'rental_end' => Carbon::parse($data['end'])->toDateString(),
        ]);

        return back()->with('success', 'Ketersediaan diperbarui untuk '.rental_range_label().'.');
    }
}
