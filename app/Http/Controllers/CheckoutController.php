<?php

namespace App\Http\Controllers;

use App\Services\Cart;
use App\Services\CreateBooking;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show()
    {
        $lines = Cart::lines();
        if (! $lines) {
            return redirect()->route('cart.index');
        }
        $totals = Cart::totals($lines);

        // Tanggal yang pernah diisi (mis. kembali dari error) dipakai sebagai isian awal
        $dates = ['start' => session('rental_start'), 'end' => session('rental_end')];

        return view('shop.checkout', compact('lines', 'totals', 'dates'));
    }

    public function store(Request $request)
    {
        $data = CreateBooking::validate($request);
        if (! Cart::raw()) {
            return redirect()->route('cart.index');
        }

        session(['rental_start' => $data['start_date'], 'rental_end' => $data['end_date']]);
        $booking = CreateBooking::record($data, Cart::raw(), $request->user()?->id);

        Cart::clear();
        remember_guest_order($booking->id); // agar muncul di My Bookings walau tanpa login

        return redirect()->route('orders.summary', $booking->access_token)
            ->with('success', "Pesanan {$booking->code} tersimpan. Lanjutkan negosiasi harga lewat WhatsApp.");
    }
}
