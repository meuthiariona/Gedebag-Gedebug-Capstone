<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $bookings = Booking::with('user')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%$search%")->orWhere('event_name', 'like', "%$search%")
                ->orWhere('customer_name', 'like', "%$search%")->orWhere('customer_phone', 'like', "%$search%")))
            ->latest()
            ->paginate(20)->withQueryString();

        return view('admin.bookings.index', compact('bookings', 'status', 'search'));
    }

    public function show(Booking $booking)
    {
        $booking->load(['items', 'logs', 'payments', 'user']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'event_name' => ['required', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'venue_name' => ['required', 'max:150'],
            'venue_address' => ['nullable', 'max:255'],
            'venue_note' => ['nullable', 'max:150'],
            'expected_pax' => ['nullable', 'integer'],
            'pic_name' => ['nullable', 'max:100'],
            'pic_phone' => ['nullable', 'max:30'],
            'delivery_point' => ['nullable', 'max:150'],
            'status' => ['required', 'in:'.implode(',', array_keys(Booking::STATUSES))],
            'stage' => ['required', 'integer', 'min:0', 'max:'.(count(Booking::STAGES) - 1)],
            'phase_title' => ['nullable', 'max:150'],
            'phase_note' => ['nullable', 'max:255'],
            'fleet_name' => ['nullable', 'max:100'],
            'driver_name' => ['nullable', 'max:150'],
            'site_instructions' => ['nullable', 'max:1000'],
            'crew_note' => ['nullable', 'max:255'],
            'inspection_label' => ['nullable', 'max:60'],
            'discount' => ['nullable'],
            'dp_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'max:1000'],
        ]);
        $data['discount'] = (int) preg_replace('/\D/', '', (string) ($data['discount'] ?? 0));
        $maxDiscount = $booking->subtotal + $booking->logistics_fee - $booking->amount_paid;
        if ($data['discount'] > max(0, $maxDiscount)) {
            return back()->withInput()->withErrors(['discount' => 'Diskon terlalu besar: total tagihan tidak boleh lebih kecil dari yang sudah dibayar ('.rupiah($booking->amount_paid).'). Diskon maksimal '.rupiah(max(0, $maxDiscount)).'.']);
        }
        $stage = (int) $data['stage'];
        unset($data['stage']);

        if ($data['status'] !== 'pending') {
            $data['hold_expires_at'] = null;
        }
        $booking->update($data);
        if ($stage !== $booking->stage) {
            $booking->advanceTo($stage, $data['phase_title'] ?? null);
        }
        $booking->recalc();

        return back()->with('success', 'Booking diperbarui.');
    }

    public function storeItem(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'name' => ['required', 'max:150'],
            'description' => ['nullable', 'max:255'],
            'category' => ['nullable', 'max:60'],
            'item_type' => ['required', 'in:product,package,addon,service,talent,crew'],
            'qty' => ['required', 'integer', 'min:1'],
            'days' => ['required', 'integer', 'min:1'],
            'rate' => ['required'],
        ]);
        $data['rate'] = (int) preg_replace('/\D/', '', $data['rate']);
        $data['subtotal'] = $data['rate'] * $data['qty'] * $data['days'];
        $booking->items()->create($data);
        $booking->recalc();

        return back()->with('success', 'Item ditambahkan.');
    }

    public function destroyItem(BookingItem $item)
    {
        $booking = $item->booking;
        if ($booking->total - $item->subtotal < $booking->amount_paid) {
            return back()->withErrors(['item' => 'Item tidak bisa dihapus: total tagihan akan lebih kecil dari yang sudah dibayar ('.rupiah($booking->amount_paid).'). Hapus/kurangi pembayaran dulu.']);
        }
        $item->delete();
        $booking->recalc();

        return back()->with('success', 'Item dihapus.');
    }

    public function storePayment(Request $request, Booking $booking)
    {
        if ($booking->remaining <= 0) {
            return back()->withErrors(['amount' => 'Booking ini sudah lunas, tidak bisa mencatat pembayaran lagi.']);
        }

        $request->merge(['amount' => (int) preg_replace('/\D/', '', (string) $request->input('amount'))]);
        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:'.$booking->remaining],
            'method' => ['required', 'max:50'],
            'reference' => ['nullable', 'max:100'],
        ], [
            'amount.max' => 'Nominal melebihi sisa tagihan ('.rupiah($booking->remaining).').',
            'amount.min' => 'Nominal pembayaran harus diisi.',
        ], ['amount' => 'nominal']);

        $payment = $booking->payments()->create($data + ['status' => 'verified', 'paid_at' => now()]);
        $booking->applyPayment($payment);

        return back()->with('success', 'Pembayaran '.rupiah($payment->amount).' dicatat.');
    }

    public function verifyPayment(Request $request, Payment $payment)
    {
        $action = $request->input('action');
        if ($payment->status === 'pending' && $action === 'verify') {
            if ($payment->amount > $payment->booking->remaining) {
                return back()->withErrors(['amount' => 'Nominal bukti bayar ('.rupiah($payment->amount).') melebihi sisa tagihan ('.rupiah($payment->booking->remaining).'). Tolak, lalu catat ulang dengan nominal yang benar.']);
            }
            $payment->update(['status' => 'verified']);
            $payment->booking->applyPayment($payment);
        } elseif ($payment->status === 'pending' && $action === 'reject') {
            $payment->update(['status' => 'rejected']);
        }

        return back()->with('success', 'Status pembayaran diperbarui.');
    }

    /** Hapus pembayaran yang salah input. Jika sudah terverifikasi, jumlah dibayar ikut dikurangi. */
    public function destroyPayment(Payment $payment)
    {
        $booking = $payment->booking;
        if ($payment->status === 'verified') {
            $booking->update(['amount_paid' => max(0, $booking->amount_paid - $payment->amount)]);
        }
        $payment->delete();

        return back()->with('success', 'Pembayaran '.rupiah($payment->amount).' dihapus.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', 'Booking dihapus.');
    }
}
