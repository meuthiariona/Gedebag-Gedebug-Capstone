<?php

namespace App\Services;

use App\Models\BookingItem;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Support\Carbon;

/**
 * Menghitung sisa stok untuk rentang tanggal tertentu berdasarkan booking aktif
 * (confirmed, atau pending yang masa hold-nya belum habis).
 */
class Availability
{
    public static function reserved(string $type, int $id, Carbon $start, Carbon $end, ?int $exceptBooking = null): int
    {
        return (int) BookingItem::query()
            ->where('item_type', $type)
            ->where('item_id', $id)
            ->whereHas('booking', function ($q) use ($start, $end, $exceptBooking) {
                $q->where('start_date', '<=', $end->toDateString())
                    ->where('end_date', '>=', $start->toDateString())
                    ->when($exceptBooking, fn ($q) => $q->where('id', '!=', $exceptBooking))
                    ->where(function ($q) {
                        $q->where('status', 'confirmed')
                            ->orWhere(fn ($q) => $q->where('status', 'pending')
                                ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now())));
                    });
            })
            ->sum('qty');
    }

    public static function product(Product $product, ?Carbon $start = null, ?Carbon $end = null): int
    {
        [$s, $e] = $start ? [$start, $end] : rental_dates();

        return max(0, $product->stock - self::reserved('product', $product->id, $s, $e));
    }

    public static function package(Package $package, ?Carbon $start = null, ?Carbon $end = null): int
    {
        [$s, $e] = $start ? [$start, $end] : rental_dates();

        return max(0, $package->stock - self::reserved('package', $package->id, $s, $e));
    }
}
