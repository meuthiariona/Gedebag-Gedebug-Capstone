<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $items = $request->user()->wishlists()->with('wishable')->latest()->get()->pluck('wishable')->filter();

        return view('shop.wishlist', compact('items'));
    }

    public function toggle(Request $request, string $type, int $id)
    {
        $class = match ($type) {
            'product' => Product::class,
            'package' => Package::class,
            default => abort(404),
        };
        $class::findOrFail($id);

        $existing = $request->user()->wishlists()->where('wishable_type', $class)->where('wishable_id', $id)->first();
        if ($existing) {
            $existing->delete();
            $saved = false;
        } else {
            $request->user()->wishlists()->create(['wishable_type' => $class, 'wishable_id' => $id]);
            $saved = true;
        }

        if ($request->wantsJson()) {
            return response()->json(['saved' => $saved]);
        }

        return back()->with('success', $saved ? 'Disimpan ke wishlist.' : 'Dihapus dari wishlist.');
    }
}
