<?php

use App\Models\Setting;
use Illuminate\Support\Carbon;

if (! function_exists('media_url')) {
    /** URL gambar: mendukung URL eksternal maupun file upload di storage/public. */
    function media_url(?string $path): string
    {
        if (! $path) {
            return asset('images/placeholder.svg');
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('rupiah')) {
    function rupiah($amount): string
    {
        return 'Rp '.number_format((int) $amount, 0, ',', '.');
    }
}

if (! function_exists('wa_number')) {
    /** Ubah nomor HP ke format wa.me: hanya angka, awalan 0 → 62. */
    function wa_number(?string $phone): string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }
}

if (! function_exists('guest_order_ids')) {
    /** ID pesanan yang dibuat dari browser ini tanpa login (disimpan di cookie terenkripsi). */
    function guest_order_ids(): array
    {
        $ids = json_decode((string) request()->cookie('guest_orders', '[]'), true);

        return array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
    }
}

if (! function_exists('remember_guest_order')) {
    /** Simpan pesanan ke cookie browser agar muncul di My Bookings walau tanpa login. */
    function remember_guest_order(int $bookingId): void
    {
        $ids = array_values(array_unique([...guest_order_ids(), $bookingId]));
        \Illuminate\Support\Facades\Cookie::queue('guest_orders', json_encode(array_slice($ids, -50)), 60 * 24 * 365);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        try {
            return Setting::get($key, $default);
        } catch (\Throwable) {
            return $default;
        }
    }
}

if (! function_exists('rental_dates')) {
    /** Tanggal sewa aktif yang dipilih user lewat "Event Availability Engine". */
    function rental_dates(): array
    {
        $start = session('rental_start') ? Carbon::parse(session('rental_start')) : now()->addDays(7)->startOfDay();
        $end = session('rental_end') ? Carbon::parse(session('rental_end')) : $start->copy()->addDays(2);
        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return [$start, $end, (int) $start->diffInDays($end) + 1];
    }
}

if (! function_exists('rental_range_label')) {
    function rental_range_label(): string
    {
        [$s, $e] = rental_dates();

        return $s->format('m') === $e->format('m') ? $s->format('j').'–'.$e->format('j M') : $s->format('j M').'–'.$e->format('j M');
    }
}
