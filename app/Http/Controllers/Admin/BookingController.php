<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Payment;
use App\Models\Package;
use App\Models\Product;
use App\Services\Availability;
use App\Services\BookingInvoice;
use App\Services\CreateBooking;
use Illuminate\Validation\ValidationException;
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

    public function create()
    {
        return view('admin.bookings.create', [
            'products' => Product::active()->orderBy('name')->get(),
            'packages' => Package::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = CreateBooking::validate($request, [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.selection' => ['required', 'string', 'regex:/^(product|package):[1-9][0-9]*$/'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);
        $rows = array_map(function ($item) {
            [$type, $id] = explode(':', $item['selection']);

            return ['type' => $type, 'id' => (int) $id, 'qty' => (int) $item['qty'], 'addons' => []];
        }, $data['items']);
        $booking = CreateBooking::record($data, $rows, manual: true);

        return redirect()->route('admin.bookings.show', $booking)->with('success', 'Booking manual dibuat. Stok ditahan sementara.');
    }

    public function negotiate(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'negotiated_total' => ['required_if:decision,approve', 'nullable', 'integer', 'min:1', 'max:999999999999'],
            'customer_email' => ['nullable', 'email', 'max:150'],
        ]);

        Availability::transaction(function () use ($booking, $data) {
            $booking = Booking::findOrFail($booking->id);
            if ($booking->status !== 'pending' || ($booking->amount_paid > 0
                && ($data['decision'] !== 'approve' || (int) $data['negotiated_total'] !== (int) $booking->negotiated_total))) {
                throw ValidationException::withMessages(['decision' => 'Total harga dan keputusan tidak bisa diubah setelah ada pembayaran.']);
            }

            if ($data['decision'] === 'reject') {
                $booking->update([
                    'status' => 'cancelled',
                    'negotiation_status' => 'rejected',
                    'hold_expires_at' => null,
                    'phase_title' => 'Pesanan ditolak',
                    'phase_note' => 'Reservasi sementara dilepas.',
                ]);

                return;
            }

            Availability::ensureBooking($booking);
            $booking->update([
                'negotiation_status' => 'approved',
                'initial_estimate' => $booking->initial_estimate ?? $booking->total,
                'negotiated_total' => $data['negotiated_total'],
                'total' => $data['negotiated_total'],
                'customer_email' => $data['customer_email'] ?? $booking->customer_email,
                'hold_expires_at' => $booking->hold_expires_at?->isFuture()
                    ? $booking->hold_expires_at
                    : now()->addHours((int) setting('hold_hours', 48)),
                'phase_title' => 'Harga disetujui, menunggu pembayaran',
                'phase_note' => 'Booking dikonfirmasi setelah pembayaran mencapai nominal DP.',
            ]);
        });

        return back()->with('success', $data['decision'] === 'approve' ? 'Harga disetujui. Booking menunggu pembayaran.' : 'Pesanan ditolak dan stok dilepas.');
    }

    public function updateInvoiceEmail(Request $request, Booking $booking)
    {
        $email = $request->validate(['customer_email' => ['required', 'email', 'max:150']])['customer_email'];

        Availability::transaction(function () use ($booking, $email) {
            $booking = Booking::lockForUpdate()->findOrFail($booking->id);
            if (! $booking->invoice_issued_at || $booking->invoice_emailed_at) {
                throw ValidationException::withMessages(['customer_email' => 'Email hanya bisa diubah di sini sebelum invoice terkirim.']);
            }
            $booking->update(['customer_email' => $email]);
        });
        app(BookingInvoice::class)->send($booking->id);

        return back()->with('success', 'Alamat email invoice diperbarui.');
    }

    public function show(Booking $booking)
    {
        $booking->load(['items', 'logs', 'payments', 'user']);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'products' => Product::active()->orderBy('name')->get(),
            'packages' => Package::active()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Booking $booking)
    {
        // RBAC tingkat field: Admin Operasional hanya boleh mengubah data operasional,
        // Admin Keuangan hanya diskon & DP. Field di luar izinnya tidak divalidasi & tidak disimpan.
        $user = $request->user();
        $rules = [];
        if ($user->hasPermission('bookings.manage')) {
            $rules += [
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
                'notes' => ['nullable', 'max:1000'],
            ];
        }
        if ($user->hasPermission('finance.manage')) {
            $rules += [
                'discount' => ['nullable'],
                'dp_percent' => ['required', 'integer', 'min:0', 'max:100'],
            ];
        }
        $data = $request->validate($rules);

        $stage = $data['stage'] ?? null;
        unset($data['stage']);

        Availability::transaction(function () use ($booking, $data, $stage) {
            $booking = Booking::findOrFail($booking->id);
            if ($booking->negotiated_total !== null) {
                unset($data['discount']);
            }
            if (array_key_exists('discount', $data)) {
                $data['discount'] = (int) preg_replace('/\D/', '', (string) ($data['discount'] ?? 0));
                $maxDiscount = $booking->subtotal + $booking->logistics_fee - $booking->amount_paid;
                if ($data['discount'] > max(0, $maxDiscount)) {
                    throw ValidationException::withMessages(['discount' => 'Diskon terlalu besar: total tagihan tidak boleh lebih kecil dari yang sudah dibayar ('.rupiah($booking->amount_paid).'). Diskon maksimal '.rupiah(max(0, $maxDiscount)).'.']);
                }
            }

            $status = $data['status'] ?? $booking->status;
            if ($booking->negotiation_status !== null && $status !== $booking->status
                && ($booking->status === 'cancelled' || ($booking->status === 'pending' && $status !== 'cancelled'))) {
                throw ValidationException::withMessages(['status' => 'Gunakan ACC dan konfirmasi pembayaran untuk mengonfirmasi booking.']);
            }
            if ($booking->negotiation_status !== null && $booking->status === 'pending'
                && $stage !== null && (int) $stage > 0) {
                throw ValidationException::withMessages(['stage' => 'DP harus dikonfirmasi sebelum mengubah tahap operasional ini.']);
            }
            if ($booking->negotiation_status !== null && array_key_exists('dp_percent', $data)
                && $booking->amount_paid > 0 && (int) $data['dp_percent'] !== (int) $booking->dp_percent) {
                throw ValidationException::withMessages(['dp_percent' => 'Persentase DP tidak bisa diubah setelah ada pembayaran.']);
            }
            if (isset($data['status']) && $status !== 'pending') {
                $data['hold_expires_at'] = null;
            }

            $booking->fill($data);
            if (in_array($status, ['confirmed', 'pending'], true)
                && $booking->isDirty(['start_date', 'end_date', 'status'])) {
                Availability::ensureBooking($booking);
            }
            $booking->save();
            if ($stage !== null && (int) $stage !== $booking->stage) {
                $booking->advanceTo((int) $stage, $data['phase_title'] ?? null);
            }
            $booking->recalc();
        });

        return back()->with('success', 'Booking diperbarui.');
    }

    public function storeItem(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'name' => ['required_without:item_id', 'nullable', 'string', 'max:150'],
            'item_id' => ['nullable', 'string', 'regex:/^(product|package):[1-9][0-9]*$/'],
            'description' => ['nullable', 'max:255'],
            'category' => ['nullable', 'max:60'],
            'item_type' => ['required', 'in:product,package,addon,service,talent,crew'],
            'qty' => ['required', 'integer', 'min:1'],
            'days' => ['required', 'integer', 'min:1'],
            'rate' => ['required'],
        ]);
        $data['rate'] = (int) preg_replace('/\D/', '', $data['rate']);
        $data['subtotal'] = $data['rate'] * $data['qty'] * $data['days'];
        Availability::transaction(function () use ($booking, $data) {
            $booking = Booking::findOrFail($booking->id);
            if (! empty($data['item_id'])) {
                [$data['item_type'], $id] = explode(':', $data['item_id']);
                $model = $data['item_type'] === 'product' ? Product::active()->find($id) : Package::active()->find($id);
                if (! $model) {
                    throw ValidationException::withMessages(['item_id' => 'Pilih produk atau paket yang sesuai dengan tipe item.']);
                }
                $data['item_id'] = $model->id;
                $data['name'] = $model->name;
                $data['inventory_components'] = $data['item_type'] === 'package' ? Availability::components($model->id) : null;
            } elseif ($booking->negotiation_status !== null && in_array($data['item_type'], ['product', 'package'], true)) {
                throw ValidationException::withMessages(['item_id' => 'Hubungkan produk atau paket ke inventori untuk mereservasi stok.']);
            }
            $booking->items()->create($data);
            if (in_array($booking->status, ['pending', 'confirmed'], true)) {
                Availability::ensureBooking($booking);
            }
            $booking->recalc();
        });

        return back()->with('success', 'Item ditambahkan.');
    }

    public function destroyItem(BookingItem $item)
    {
        Availability::transaction(function () use ($item) {
            $item = BookingItem::findOrFail($item->id);
            $booking = $item->booking;
            if ($booking->negotiated_total === null && $booking->total - $item->subtotal < $booking->amount_paid) {
                throw ValidationException::withMessages(['item' => 'Item tidak bisa dihapus karena total tagihan akan lebih kecil dari pembayaran.']);
            }
            $item->delete();
            $booking->recalc();
        });

        return back()->with('success', 'Item dihapus.');
    }

    public function storePayment(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'amount' => ['required', 'regex:/^\d+(?:[.,]\d{3})*$/'],
            'method' => ['required', 'string', 'max:50'],
            'reference' => ['nullable', 'string', 'max:100'],
            'submission_key' => ['nullable', 'uuid'],
        ]);
        $data['amount'] = (int) preg_replace('/\D/', '', (string) $data['amount']);

        Availability::transaction(function () use ($booking, $data) {
            $booking = Booking::findOrFail($booking->id);
            if (! empty($data['submission_key']) && $booking->payments()->where('submission_key', $data['submission_key'])->exists()) {
                return;
            }
            $this->ensurePayable($booking, $data['amount']);
            $payment = $booking->payments()->create($data + ['status' => 'verified', 'paid_at' => now()]);
            $booking->applyPayment($payment);
        });

        app(BookingInvoice::class)->send($booking->id);

        return back()->with('success', 'Pembayaran '.rupiah($data['amount']).' dicatat.');
    }

    public function verifyPayment(Request $request, Payment $payment)
    {
        $action = $request->validate(['action' => ['required', 'in:verify,reject']])['action'];

        Availability::transaction(function () use ($payment, $action) {
            $payment = Payment::findOrFail($payment->id);
            if ($payment->status !== 'pending') {
                return;
            }
            if ($action === 'reject') {
                $payment->update(['status' => 'rejected']);

                return;
            }

            $booking = $payment->booking;
            $this->ensurePayable($booking, $payment->amount);
            $payment->update(['status' => 'verified', 'paid_at' => $payment->paid_at ?? now()]);
            $booking->applyPayment($payment);
        });

        if ($action === 'verify') {
            app(BookingInvoice::class)->send($payment->booking_id);
        }

        return back()->with('success', 'Status pembayaran diperbarui.');
    }

    private function ensurePayable(Booking $booking, int $amount): void
    {
        if ($booking->status === 'cancelled' || $booking->negotiation_status === 'pending') {
            throw ValidationException::withMessages(['amount' => 'ACC harga booking sebelum mencatat pembayaran.']);
        }
        if ($booking->negotiation_status === 'approved' && $booking->status === 'pending'
            && ! $booking->hold_expires_at?->isFuture()) {
            throw ValidationException::withMessages(['amount' => 'Waktu penahanan stok habis. ACC ulang booking untuk mengecek stok.']);
        }
        if ($amount < 1 || $amount > $booking->remaining) {
            throw ValidationException::withMessages(['amount' => 'Nominal harus lebih dari nol dan tidak melebihi sisa tagihan ('.rupiah($booking->remaining).').']);
        }
        if ($booking->status === 'pending') {
            Availability::ensureBooking($booking);
        }
    }

    /** Hapus pembayaran yang salah input. Jika sudah terverifikasi, jumlah dibayar ikut dikurangi. */
    public function destroyPayment(Payment $payment)
    {
        Availability::transaction(function () use ($payment) {
            $payment = Payment::findOrFail($payment->id);
            $booking = $payment->booking;
            if ($payment->status === 'verified') {
                $booking->update(['amount_paid' => max(0, $booking->amount_paid - $payment->amount)]);
                if ($booking->negotiation_status === 'approved' && $booking->status === 'confirmed'
                    && $booking->stage <= 1 && ($booking->amount_paid === 0 || $booking->amount_paid < $booking->dp_amount)) {
                    $booking->update([
                        'status' => 'pending',
                        'hold_expires_at' => now()->addHours((int) setting('hold_hours', 48)),
                        'phase_title' => 'Harga disetujui, menunggu pembayaran',
                    ]);
                    $booking->advanceTo(0);
                }
            }
            $payment->delete();
        });

        return back()->with('success', 'Pembayaran '.rupiah($payment->amount).' dihapus.');
    }

    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()->route('admin.bookings.index')->with('success', 'Booking dihapus.');
    }
}
