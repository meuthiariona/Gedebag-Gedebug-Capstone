<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hold_expires_at' => 'datetime',
    ];

    public const STAGES = [
        ['label' => 'Booking Created', 'icon' => 'check'],
        ['label' => 'DP Confirmed', 'icon' => 'check'],
        ['label' => 'Equipment Reserved', 'icon' => 'check'],
        ['label' => 'Preparing Gear', 'icon' => 'wrench'],
        ['label' => 'Delivered', 'icon' => 'truck'],
        ['label' => 'Event Completed', 'icon' => 'party-popper'],
        ['label' => 'Equipment Returned', 'icon' => 'package-check'],
    ];

    public const STATUSES = [
        'pending' => 'Pending Confirmation',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function logs()
    {
        return $this->hasMany(BookingLog::class)->orderBy('stage');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class)->latest();
    }

    public function getDaysAttribute(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->total - $this->amount_paid);
    }

    public function getPaidPercentAttribute(): int
    {
        return $this->total > 0 ? min(100, (int) round($this->amount_paid / $this->total * 100)) : 0;
    }

    /** Kelebihan bayar (mis. data lama / total diturunkan setelah dibayar). */
    public function getOverpaidAttribute(): int
    {
        return max(0, $this->amount_paid - $this->total);
    }

    public function getDpAmountAttribute(): int
    {
        return (int) round($this->total * $this->dp_percent / 100);
    }

    public function getPaymentStatusAttribute(): string
    {
        if ($this->total > 0 && $this->amount_paid >= $this->total) {
            return 'paid';
        }

        return $this->amount_paid > 0 ? 'dp_paid' : 'unpaid';
    }

    public function getPaymentLabelAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'FULL PAYMENT (LUNAS)',
            'dp_paid' => 'DP PAID ('.$this->paid_percent.'%)',
            default => 'DP DUE',
        };
    }

    /** Nominal yang harus dibayar pada tagihan berikutnya (DP dulu, lalu pelunasan). */
    public function getNextDueAttribute(): int
    {
        return $this->amount_paid < $this->dp_amount ? $this->dp_amount - $this->amount_paid : $this->remaining;
    }

    public function getInvoiceNoAttribute(): string
    {
        return 'INV-'.str_replace('EVT-', '', $this->code);
    }

    public function getDateRangeAttribute(): string
    {
        $s = $this->start_date;
        $e = $this->end_date;

        return $s->format('Ym') === $e->format('Ym')
            ? $s->format('d').' – '.$e->format('d M Y')
            : $s->format('d M').' – '.$e->format('d M Y');
    }

    public function logFor(int $stage): ?BookingLog
    {
        return $this->logs->firstWhere('stage', $stage);
    }

    /** Pindahkan pipeline ke tahap tertentu & catat waktu tiap tahap yang dilewati. */
    public function advanceTo(int $stage, ?string $note = null): void
    {
        $stage = max(0, min($stage, count(self::STAGES) - 1));
        for ($i = 0; $i <= $stage; $i++) {
            $this->logs()->firstOrCreate(['stage' => $i], ['occurred_at' => now(), 'note' => $i === $stage ? $note : null]);
        }
        $this->logs()->where('stage', '>', $stage)->delete();
        $this->update(['stage' => $stage]);
    }

    /** Hitung ulang subtotal/total dari baris item. Item 'service' = biaya logistik. */
    public function recalc(): void
    {
        $items = $this->items()->get();
        $logistics = (int) $items->where('item_type', 'service')->sum('subtotal');
        $subtotal = (int) $items->where('item_type', '!=', 'service')->sum('subtotal');
        $this->update([
            'subtotal' => $subtotal,
            'logistics_fee' => $logistics,
            'total' => max(0, $subtotal + $logistics - $this->discount),
        ]);
    }

    /** Terapkan pembayaran terverifikasi & majukan pipeline bila DP terpenuhi. */
    public function applyPayment(Payment $payment): void
    {
        $this->increment('amount_paid', $payment->amount);
        $this->refresh();
        if ($this->amount_paid >= $this->dp_amount && $this->status === 'pending') {
            $this->update(['status' => 'confirmed', 'hold_expires_at' => null]);
        }
        if ($this->amount_paid >= $this->dp_amount && $this->stage < 1) {
            $this->advanceTo(1, 'DP diterima via '.$payment->method);
        }
    }

    /** Link ringkasan pesanan (bisa dibuka tanpa login). */
    public function getSummaryUrlAttribute(): string
    {
        return route('orders.summary', $this->access_token);
    }

    /** Template chat WhatsApp untuk negosiasi, diisi otomatis dari detail pesanan. */
    public function whatsappMessage(): string
    {
        $items = $this->items()->get();
        $lines = [
            'Halo '.setting('site_name', 'NEXA').', saya ingin memesan dan negosiasi harga untuk pesanan berikut:',
            '',
            '*Kode Pesanan:* '.$this->code,
            '',
            '*DATA PEMESAN*',
            'Nama: '.$this->customer_name,
            'No. WhatsApp: '.$this->customer_phone,
        ];
        if ($this->customer_email) {
            $lines[] = 'Email: '.$this->customer_email;
        }
        if ($this->customer_company) {
            $lines[] = 'Perusahaan: '.$this->customer_company;
        }

        array_push($lines, '', '*DETAIL EVENT*',
            'Nama event: '.$this->event_name,
            'Tanggal: '.$this->start_date->format('j M Y').' – '.$this->end_date->format('j M Y').' ('.$this->days.' hari)',
            'Venue: '.$this->venue_name.($this->venue_note ? ' ('.$this->venue_note.')' : ''),
            'Alamat: '.$this->venue_address,
        );
        if ($this->expected_pax) {
            $lines[] = 'Perkiraan tamu: '.$this->expected_pax.' pax';
        }
        $lines[] = 'PIC lokasi: '.$this->pic_name.' ('.$this->pic_phone.')';

        $lines[] = '';
        $lines[] = '*PESANAN*';
        $no = 0;
        foreach ($items->where('item_type', '!=', 'service') as $item) {
            if ($item->item_type === 'addon') {
                $lines[] = '   + '.$item->name.' – '.rupiah($item->subtotal);
                continue;
            }
            $detail = $item->qty.'x'.($item->unit === 'hari' && $item->days > 1 ? ', '.$item->days.' hari' : '');
            $lines[] = (++$no).'. '.$item->name.' ('.$detail.') – '.rupiah($item->subtotal);
        }
        foreach ($items->where('item_type', 'service') as $item) {
            $lines[] = $item->name.' – '.rupiah($item->subtotal);
        }
        if ($this->discount) {
            $lines[] = 'Diskon – -'.rupiah($this->discount);
        }
        $lines[] = '*Estimasi total: '.rupiah($this->total).'*';

        if ($this->notes) {
            array_push($lines, '', '*Catatan:* '.$this->notes);
        }
        array_push($lines, '', 'Ringkasan pesanan: '.$this->summary_url, '', 'Mohon info harga final & ketersediaannya. Terima kasih.');

        return implode("\n", $lines);
    }

    public function whatsappUrl(): string
    {
        return 'https://wa.me/'.wa_number(setting('whatsapp', '')).'?text='.rawurlencode($this->whatsappMessage());
    }

    public static function nextCode(): string
    {
        return 'EVT-'.(10231 + (int) static::max('id'));
    }
}
