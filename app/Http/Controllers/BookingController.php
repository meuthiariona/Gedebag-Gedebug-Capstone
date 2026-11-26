<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /** Booking milik pengunjung: milik akun yang login + pesanan tamu yang tersimpan di cookie browser ini. */
    private function mine()
    {
        $ids = guest_order_ids();

        return Booking::query()->where(function ($q) use ($ids) {
            $q->whereIn('id', $ids ?: [0]);
            if (auth()->check()) {
                $q->orWhere('user_id', auth()->id());
            }
        });
    }

    public function index(Request $request)
    {
        // Validasi input (whitelist format) — tindak lanjut temuan ZAP pada parameter q & month.
        $request->validate([
            'tab' => ['nullable', 'in:upcoming,completed,cancelled'],
            'q' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ]);
        $tab = $request->query('tab', 'upcoming');
        $search = trim((string) $request->query('q'));
        $month = $request->query('month');

        $base = $this->mine();
        $counts = [
            'upcoming' => (clone $base)->whereIn('status', ['pending', 'confirmed'])->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $bookings = $this->mine()->with('items')
            ->when($tab === 'upcoming', fn ($q) => $q->whereIn('status', ['pending', 'confirmed']))
            ->when($tab === 'completed', fn ($q) => $q->where('status', 'completed'))
            ->when($tab === 'cancelled', fn ($q) => $q->where('status', 'cancelled'))
            ->when($search, fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', "%$search%")->orWhere('event_name', 'like', "%$search%")))
            ->when($month, fn ($q) => $q->whereYear('start_date', substr($month, 0, 4))->whereMonth('start_date', substr($month, 5, 2)))
            ->orderBy('start_date')
            ->get();

        $active = $this->mine()->whereIn('status', ['pending', 'confirmed'])->get();
        $summary = [
            'reserved' => $active->count(),
            'next' => $active->where('start_date', '>=', today())->sortBy('start_date')->first()?->start_date,
            'pending' => $active->sum(fn ($b) => $b->remaining),
        ];

        $months = $this->mine()->orderBy('start_date')->pluck('start_date')
            ->map(fn ($d) => $d->format('Y-m'))->unique()->values();

        return view('bookings.index', compact('bookings', 'counts', 'tab', 'search', 'month', 'months', 'summary'));
    }

    public function show(Booking $booking)
    {
        $this->authorizeBooking($booking);
        $booking->load(['items', 'logs', 'payments', 'feedback']);

        return view('bookings.show', compact('booking'));
    }

    public function storeFeedback(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);
        abort_if($request->user()?->isAdmin(), 403);
        abort_unless($booking->status === 'completed', 403);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);
        $feedback = $booking->feedback()->firstOrCreate([], $data);

        return redirect()->route('bookings.show', $booking)->with('success', $feedback->wasRecentlyCreated
            ? 'Terima kasih. Feedback sudah dikirim.'
            : 'Feedback untuk booking ini sudah pernah dikirim.');
    }

    public function invoice(Booking $booking)
    {
        $this->authorizeBooking($booking);
        abort_unless($booking->invoice_available, 404);

        return $this->renderInvoice($booking);
    }

    public function emailedInvoice(Request $request, Booking $booking)
    {
        abort_unless($booking->invoice_issued_at && $booking->access_token
            && hash_equals($booking->access_token, $request->string('token')->toString()), 404);

        return $this->renderInvoice($booking);
    }

    private function renderInvoice(Booking $booking)
    {
        $booking->load(['items', 'payments', 'user']);

        return response()->view('bookings.invoice', compact('booking'))
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /**
     * Ringkasan pesanan + tombol negosiasi WhatsApp.
     * Bisa dibuka tanpa login lewat link rahasia (access_token) yang didapat setelah checkout.
     */
    public function summary(string $token)
    {
        $booking = Booking::where('access_token', $token)->with('items')->firstOrFail();
        remember_guest_order($booking->id); // link ini membuka akses booking di browser ini

        // Sudah chat admin untuk nego → tampilkan halaman detail booking
        if ($booking->wa_contacted_at || $booking->status === 'completed') {
            return redirect()->route('bookings.show', $booking);
        }

        return view('bookings.summary', compact('booking'));
    }

    /** Tombol "Chat via WhatsApp": catat bahwa customer sudah menghubungi admin, lalu buka WhatsApp. */
    public function whatsapp(string $token)
    {
        $booking = Booking::where('access_token', $token)->firstOrFail();
        remember_guest_order($booking->id);

        if (! $booking->wa_contacted_at && in_array($booking->negotiation_status, [null, 'pending'], true)
            && $booking->status === 'pending') {
            $booking->update([
                'wa_contacted_at' => now(),
                'phase_title' => 'Negosiasi harga via WhatsApp',
                'phase_note' => 'Customer sudah menghubungi admin. Harga final & jadwal pembayaran sedang disepakati.',
            ]);
        }

        return redirect()->away($booking->whatsappUrl());
    }

    private function authorizeBooking(Booking $booking): void
    {
        $allowed = in_array($booking->id, guest_order_ids(), true)
            || (auth()->check() && ($booking->user_id === auth()->id() || auth()->user()->isAdmin()));
        abort_unless($allowed, 403);
    }
}
