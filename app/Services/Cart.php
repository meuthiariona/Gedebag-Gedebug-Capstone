<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Product;
use Illuminate\Support\Carbon;

/** Keranjang berbasis session. Harga selalu dihitung ulang dari database. */
class Cart
{
    public static function raw(): array
    {
        return session('cart', []);
    }

    public static function count(): int
    {
        return array_sum(array_column(self::raw(), 'qty'));
    }

    public static function addProduct(Product $product, int $qty = 1): void
    {
        $cart = self::raw();
        $key = 'product-'.$product->id;
        $cart[$key] = ['type' => 'product', 'id' => $product->id, 'qty' => ($cart[$key]['qty'] ?? 0) + $qty, 'addons' => []];
        session(['cart' => $cart]);
    }

    public static function addPackage(Package $package, array $addonIds = []): void
    {
        $cart = self::raw();
        $addonIds = $package->addons()->whereIn('id', $addonIds)->pluck('id')->sort()->values()->all();
        $key = 'package-'.$package->id.'-'.implode('.', $addonIds);
        $cart[$key] = ['type' => 'package', 'id' => $package->id, 'qty' => ($cart[$key]['qty'] ?? 0) + 1, 'addons' => $addonIds];
        session(['cart' => $cart]);
    }

    public static function update(string $key, int $qty): void
    {
        $cart = self::raw();
        if (isset($cart[$key])) {
            if ($qty < 1) {
                unset($cart[$key]);
            } else {
                $cart[$key]['qty'] = $qty;
            }
        }
        session(['cart' => $cart]);
    }

    public static function clear(): void
    {
        session()->forget('cart');
    }

    /**
     * Baris keranjang lengkap dengan model, harga & ketersediaan.
     *
     * Tanpa tanggal (halaman keranjang): item harian dihitung 1 hari dan ketersediaan = total stok.
     * Dengan tanggal (checkout): harga mengikuti jumlah hari & stok dicek terhadap booking lain di rentang itu.
     */
    public static function lines(?Carbon $start = null, ?Carbon $end = null, ?array $rows = null): array
    {
        $dated = $start && $end;
        $days = $dated ? (int) $start->diffInDays($end) + 1 : 1;
        $lines = [];

        foreach (($rows ?? self::raw()) as $key => $row) {
            if ($row['type'] === 'product') {
                $model = Product::active()->with('category')->find($row['id']);
                if (! $model) {
                    continue;
                }
                $mult = $model->unit === 'hari' ? $days : 1;
                $lines[] = [
                    'key' => $key, 'type' => 'product', 'model' => $model, 'qty' => $row['qty'], 'addons' => collect(),
                    'name' => $model->name, 'subtitle' => $model->subtitle, 'category' => $model->category?->name,
                    'image' => $model->image_url, 'rate' => $model->price, 'unit' => $model->unit, 'days' => $mult,
                    'subtotal' => $model->price * $row['qty'] * $mult,
                    'available' => $dated ? Availability::product($model, $start, $end) : $model->stock,
                ];
            } else {
                $model = Package::active()->find($row['id']);
                if (! $model) {
                    continue;
                }
                $addons = $model->addons()->whereIn('id', $row['addons'] ?? [])->get();
                $unitPrice = $model->price + $addons->sum('price');
                $lines[] = [
                    'key' => $key, 'type' => 'package', 'model' => $model, 'qty' => $row['qty'], 'addons' => $addons,
                    'name' => $model->name, 'subtitle' => $model->summary, 'category' => 'Package',
                    'image' => $model->image_url, 'rate' => $unitPrice, 'unit' => $model->unit, 'days' => 1,
                    'subtotal' => $unitPrice * $row['qty'],
                    'available' => $dated ? Availability::package($model, $start, $end) : $model->stock,
                ];
            }
        }

        return $lines;
    }

    public static function totals(array $lines): array
    {
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $logistics = $lines ? (int) setting('logistics_fee', 200000) : 0;

        return ['subtotal' => $subtotal, 'logistics' => $logistics, 'total' => $subtotal + $logistics];
    }
}
