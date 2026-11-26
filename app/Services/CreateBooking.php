<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    public static function validate(Request $request, array $rules = []): array
    {
        return $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{8,}$/'],
            'customer_email' => ['nullable', 'email', 'max:150'],
            'customer_company' => ['nullable', 'string', 'max:150'],
            'event_name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date', 'after:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.now()->addYears(2)->toDateString()],
            'venue_name' => ['required', 'string', 'max:150'],
            'venue_address' => ['required', 'string', 'max:255'],
            'venue_note' => ['nullable', 'string', 'max:150'],
            'expected_pax' => ['nullable', 'integer', 'min:1'],
            'pic_name' => ['required', 'string', 'max:100'],
            'pic_phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ] + $rules, [
            'customer_phone.regex' => 'Nomor WhatsApp tidak valid.',
            'start_date.after' => 'Tanggal mulai acara paling cepat besok.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'end_date.before_or_equal' => 'Tanggal selesai maksimal 2 tahun dari sekarang.',
        ], [
            'start_date' => 'tanggal mulai acara', 'end_date' => 'tanggal selesai acara',
            'customer_name' => 'nama lengkap', 'customer_phone' => 'nomor WhatsApp', 'customer_email' => 'email',
            'event_name' => 'nama event', 'venue_name' => 'nama venue', 'venue_address' => 'alamat venue',
            'pic_name' => 'PIC di lokasi', 'pic_phone' => 'no. HP PIC',
        ]);
    }

    public static function record(array $data, array $rows, ?int $userId = null, bool $manual = false): Booking
    {
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        unset($data['start_date'], $data['end_date'], $data['items']);

        return Availability::transaction(function () use ($data, $rows, $userId, $manual, $start, $end) {
            $lines = Cart::lines($start, $end, $rows);
            if (! $lines || count($lines) !== count($rows)) {
                throw ValidationException::withMessages(['items' => 'Barang atau paket sudah tidak tersedia. Pilih ulang item booking.']);
            }
            $totals = Cart::totals($lines);
            $booking = Booking::create($data + [
                'code' => Booking::nextCode(),
                'access_token' => Str::random(40),
                'user_id' => $userId,
                'start_date' => $start,
                'end_date' => $end,
                'status' => 'pending',
                'negotiation_status' => 'pending',
                'initial_estimate' => $totals['total'],
                'subtotal' => $totals['subtotal'],
                'logistics_fee' => $totals['logistics'],
                'total' => $totals['total'],
                'dp_percent' => (int) setting('dp_percent', 50),
                'hold_expires_at' => now()->addHours((int) setting('hold_hours', 48)),
                'phase_title' => 'Menunggu negosiasi via WhatsApp',
                'phase_note' => 'Harga final & pembayaran disepakati bersama tim kami lewat WhatsApp.',
            ]);

            foreach ($lines as $line) {
                $booking->items()->create([
                    'item_type' => $line['type'],
                    'item_id' => $line['model']->id,
                    'inventory_components' => $line['type'] === 'package' ? Availability::components($line['model']->id) : null,
                    'name' => $line['name'],
                    'description' => $line['subtitle'],
                    'category' => $line['category'],
                    'image' => $line['model']->image,
                    'qty' => $line['qty'],
                    'days' => $line['days'],
                    'unit' => $line['unit'],
                    'rate' => $line['type'] === 'package' ? $line['model']->price : $line['rate'],
                    'subtotal' => $line['type'] === 'package' ? $line['model']->price * $line['qty'] : $line['subtotal'],
                ]);
                foreach ($line['addons'] as $addon) {
                    $booking->items()->create([
                        'item_type' => 'addon', 'item_id' => $addon->id, 'name' => $addon->name,
                        'description' => $addon->description, 'category' => 'Add-on', 'qty' => $line['qty'],
                        'unit' => $addon->unit, 'rate' => $addon->price, 'subtotal' => $addon->price * $line['qty'],
                    ]);
                }
            }

            if ($totals['logistics'] > 0) {
                $booking->items()->create([
                    'item_type' => 'service', 'name' => 'Logistics, Rigging & Delivery Fee',
                    'description' => 'Enclosed freight transport, stage rigging setup & post-event teardown',
                    'category' => 'Freight', 'qty' => 1, 'unit' => 'trip', 'rate' => $totals['logistics'], 'subtotal' => $totals['logistics'],
                ]);
            }

            Availability::ensureBooking($booking);
            $booking->advanceTo(0, $manual ? 'Booking dibuat oleh admin' : 'Booking dibuat via website');

            return $booking;
        });
    }
}
