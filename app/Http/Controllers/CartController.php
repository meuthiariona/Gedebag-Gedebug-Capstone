<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use App\Services\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $lines = Cart::lines();
        $totals = Cart::totals($lines);

        return view('shop.cart', compact('lines', 'totals'));
    }

    public function addProduct(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);
        Cart::addProduct($product, max(1, (int) $request->input('qty', 1)));

        return back()->with('success', $product->name.' ditambahkan ke keranjang.');
    }

    public function addPackage(Request $request, Package $package)
    {
        abort_unless($package->is_active, 404);
        Cart::addPackage($package, (array) $request->input('addons', []));

        if ($request->boolean('checkout')) {
            return redirect()->route('cart.index');
        }

        return back()->with('success', $package->name.' ditambahkan ke keranjang.');
    }

    public function update(Request $request, string $key)
    {
        Cart::update($key, (int) $request->input('qty'));

        return back();
    }

    public function remove(string $key)
    {
        Cart::update($key, 0);

        return back()->with('success', 'Item dihapus dari keranjang.');
    }
}
