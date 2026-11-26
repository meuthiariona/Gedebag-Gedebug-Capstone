<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Package;
use App\Models\PackageItem;
use App\Models\Product;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Availability
{
    /** Serialize inventory writes before reading stock on both SQLite and MySQL. */
    public static function transaction(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            DB::table('settings')->upsert([['key' => 'erp_inventory_lock', 'value' => '1']], ['key'], ['value']);

            return $operation();
        }, 3);
    }

    /** Product quantities in one package, grouped because a product may appear twice. */
    public static function components(int $packageId): array
    {
        return PackageItem::where('package_id', $packageId)->whereNotNull('product_id')
            ->selectRaw('product_id, SUM(qty) AS units')->groupBy('product_id')
            ->pluck('units', 'product_id')->map(fn ($qty) => (int) $qty)->all();
    }

    public static function reserved(string $type, int $id, Carbon $start, Carbon $end, ?int $exceptBooking = null): int
    {
        return max(self::dailyReservations($type, $id, $start, $end, $exceptBooking));
    }

    private static function dailyReservations(string $type, int $id, Carbon $start, Carbon $end, ?int $exceptBooking = null): array
    {
        $first = $start->toDateString();
        $last = $end->toDateString();
        $items = BookingItem::with('booking')->where(function ($q) use ($type, $id) {
            $q->where(fn ($direct) => $direct->where('item_type', $type)->where('item_id', $id));
            if ($type === 'product') {
                $q->orWhere('item_type', 'package');
            }
        })->whereHas('booking', function ($q) use ($first, $last, $exceptBooking) {
            $q->whereDate('start_date', '<=', $last)->whereDate('end_date', '>=', $first)
                ->when($exceptBooking, fn ($q) => $q->where('id', '!=', $exceptBooking))
                ->where(fn ($q) => $q->where('status', 'confirmed')
                    ->orWhere(fn ($q) => $q->where('status', 'pending')
                        ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))));
        })->get();

        $changes = [];
        $legacyComponents = [];
        foreach ($items as $item) {
            $qty = (int) $item->qty;
            if ($type === 'product' && $item->item_type === 'package') {
                $components = $item->inventory_components;
                if ($components === null && $item->item_id) {
                    $components = $legacyComponents[$item->item_id] ??= self::components($item->item_id);
                }
                $qty *= $components[$id] ?? 0;
            }
            if ($qty === 0) {
                continue;
            }

            $from = max($first, $item->booking->start_date->toDateString());
            $after = min($end, $item->booking->end_date)->copy()->addDay()->toDateString();
            $changes[$from] = ($changes[$from] ?? 0) + $qty;
            $changes[$after] = ($changes[$after] ?? 0) - $qty;
        }

        $daily = [];
        $running = 0;
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $date = $day->toDateString();
            $running += $changes[$date] ?? 0;
            $daily[$date] = $running;
        }

        return $daily ?: [$first => 0];
    }

    public static function product(Product $product, ?Carbon $start = null, ?Carbon $end = null): int
    {
        [$start, $end] = $start ? [$start, $end ?? $start] : rental_dates();

        return max(0, $product->stock - self::reserved('product', $product->id, $start, $end));
    }

    public static function productCalendar(Product $product, Carbon $start, Carbon $end): array
    {
        return array_map(fn ($reserved) => max(0, $product->stock - $reserved),
            self::dailyReservations('product', $product->id, $start, $end));
    }

    public static function package(Package $package, ?Carbon $start = null, ?Carbon $end = null): int
    {
        [$start, $end] = $start ? [$start, $end ?? $start] : rental_dates();
        $available = max(0, $package->stock - self::reserved('package', $package->id, $start, $end));
        foreach (self::components($package->id) as $productId => $qty) {
            $product = Product::findOrFail($productId);
            $available = min($available, intdiv(self::product($product, $start, $end), $qty));
        }

        return $available;
    }

    public static function ensureBooking(Booking $booking): void
    {
        $needed = ['product' => [], 'package' => []];
        foreach ($booking->items()->whereIn('item_type', ['product', 'package'])->whereNotNull('item_id')->get() as $item) {
            $type = $item->item_type;
            $id = $item->item_id;
            $needed[$type][$id] = ($needed[$type][$id] ?? 0) + $item->qty;
            if ($type === 'package') {
                foreach ($item->inventory_components ?? self::components($id) as $productId => $qty) {
                    $needed['product'][$productId] = ($needed['product'][$productId] ?? 0) + $qty * $item->qty;
                }
            }
        }
        foreach (['product' => Product::class, 'package' => Package::class] as $type => $model) {
            foreach ($needed[$type] as $id => $qty) {
                $item = $model::find($id);
                $available = $item ? max(0, $item->stock - self::reserved(
                    $type, $id, $booking->start_date, $booking->end_date, $booking->id
                )) : 0;
                if ($qty > $available) {
                    $name = $item?->name ?? 'Item inventori';
                    $range = $booking->start_date->format('j M Y').($booking->days === 1 ? '' : ' - '.$booking->end_date->format('j M Y'));
                    throw ValidationException::withMessages(['start_date' => $available > 0
                        ? "Stok {$name} hanya tersedia {$available} untuk tanggal {$range}."
                        : "{$name} sudah penuh dipesan untuk tanggal {$range}. Silakan pilih tanggal lain.",
                    ]);
                }
            }
        }
    }
}
