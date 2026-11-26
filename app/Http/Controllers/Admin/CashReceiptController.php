<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CashReceiptController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $month);
        $end = $start->copy()->addMonth();

        // Verified payments are the source of truth for this cash receipt report.
        $receipts = Payment::where('status', 'verified')->where('paid_at', '>=', $start)->where('paid_at', '<', $end);
        $received = (clone $receipts)->sum('amount');
        $payments = $receipts->with('booking')->orderByDesc('paid_at')->orderByDesc('id')->paginate(25)->withQueryString();

        $renters = Booking::whereIn('status', ['confirmed', 'completed'])
            ->whereDate('start_date', '>=', $start)->whereDate('start_date', '<', $end)
            ->get(['id', 'user_id', 'customer_phone'])
            ->map(fn (Booking $booking) => wa_number($booking->customer_phone ?: '')
                ?: ($booking->user_id ? 'user-'.$booking->user_id : 'booking-'.$booking->id))
            ->unique()->count();

        return view('admin.cash-receipts', compact('month', 'received', 'payments', 'renters'));
    }
}
