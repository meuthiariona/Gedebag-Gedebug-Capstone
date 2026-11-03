@extends('layouts.app')
@section('title', 'Booking #'.$booking->code)

@section('content')
@php
    $cls = 'w-full h-10 px-3 rounded-xl border border-line text-sm outline-none focus:border-ink mt-1';
    $field = fn ($name, $label, $type = 'text') => compact('name', 'label', 'type');
@endphp
<div class="flex flex-wrap items-center gap-2 mb-4">
    <a href="{{ route('admin.bookings.index') }}" class="text-[12px] text-muted flex items-center gap-1"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Semua booking</a>
    <div class="ml-auto flex gap-2">
        <a href="{{ route('bookings.show', $booking) }}" target="_blank" class="h-9 px-4 rounded-full bg-white border border-line text-[12px] font-semibold flex items-center gap-1.5"><i data-lucide="eye" class="w-3.5 h-3.5"></i> Tampilan customer</a>
        <a href="{{ route('bookings.invoice', $booking) }}" target="_blank" class="h-9 px-4 rounded-full bg-white border border-line text-[12px] font-semibold flex items-center gap-1.5"><i data-lucide="receipt" class="w-3.5 h-3.5"></i> Invoice</a>
        <form action="{{ route('admin.bookings.destroy', $booking) }}" method="POST" onsubmit="return confirm('Hapus booking ini permanen?')">@csrf @method('DELETE')
            <button class="h-9 px-4 rounded-full text-red-600 bg-red-50 text-[12px] font-semibold">Hapus</button>
        </form>
    </div>
</div>

<div class="grid xl:grid-cols-[1fr_380px] gap-5 items-start">
    <div class="space-y-5">
        <form action="{{ route('admin.bookings.update', $booking) }}" method="POST" class="bg-white border border-line rounded-2xl p-6">
            @csrf @method('PUT')
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">Detail & Status Operasional</h2>
                <span class="text-[12px] text-muted">Customer: <b class="text-ink">{{ $booking->customer_name }}</b>{{ $booking->customer_company ? " ({$booking->customer_company})" : "" }} · {{ $booking->customer_phone }}{{ $booking->customer_email ? " · {$booking->customer_email}" : "" }}
                    <a href="https://wa.me/{{ wa_number($booking->customer_phone) }}" target="_blank" class="ml-2 text-emerald-600 font-semibold">Chat customer</a>{{ $booking->user_id ? "" : " · tamu (tanpa akun)" }}</span>
            </div>

            <div class="grid sm:grid-cols-3 gap-4 mt-5">
                <div>
                    <label class="text-[12px] font-semibold">Status booking</label>
                    <select name="status" class="{{ $cls }} bg-white">@foreach (\App\Models\Booking::STATUSES as $k => $v)<option value="{{ $k }}" @selected($booking->status === $k)>{{ $v }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="text-[12px] font-semibold">Tahap pipeline</label>
                    <select name="stage" class="{{ $cls }} bg-white">@foreach (\App\Models\Booking::STAGES as $i => $s)<option value="{{ $i }}" @selected($booking->stage === $i)>{{ $i + 1 }}. {{ $s['label'] }}</option>@endforeach</select>
                </div>
                <div>
                    <label class="text-[12px] font-semibold">Label inspeksi</label>
                    <input name="inspection_label" value="{{ $booking->inspection_label }}" class="{{ $cls }}" placeholder="Rigging Inspected">
                </div>
                <div class="sm:col-span-3 grid sm:grid-cols-2 gap-4">
                    <div><label class="text-[12px] font-semibold">Judul fase saat ini</label><input name="phase_title" value="{{ $booking->phase_title }}" class="{{ $cls }}" placeholder="Warehouse Rigging Bay #2"></div>
                    <div><label class="text-[12px] font-semibold">Keterangan fase</label><input name="phase_note" value="{{ $booking->phase_note }}" class="{{ $cls }}" placeholder="Hardware QA diagnostics..."></div>
                </div>
            </div>

            <h3 class="text-[12px] uppercase tracking-wider text-muted font-semibold mt-6">Event & Venue</h3>
            <div class="grid sm:grid-cols-3 gap-4 mt-2">
                @foreach ([
                    $field('event_name', 'Nama event'), $field('start_date', 'Mulai', 'date'), $field('end_date', 'Selesai', 'date'),
                    $field('venue_name', 'Venue'), $field('venue_note', 'Area / ruangan'), $field('expected_pax', 'Pax', 'number'),
                    $field('venue_address', 'Alamat venue'), $field('pic_name', 'PIC'), $field('pic_phone', 'HP PIC'),
                ] as $f)
                    <div>
                        <label class="text-[12px] font-semibold">{{ $f['label'] }}</label>
                        <input type="{{ $f['type'] }}" name="{{ $f['name'] }}" value="{{ $booking->{$f['name']} instanceof \Carbon\Carbon ? $booking->{$f['name']}->toDateString() : $booking->{$f['name']} }}" class="{{ $cls }}">
                    </div>
                @endforeach
            </div>

            <h3 class="text-[12px] uppercase tracking-wider text-muted font-semibold mt-6">Logistik</h3>
            <div class="grid sm:grid-cols-3 gap-4 mt-2">
                <div><label class="text-[12px] font-semibold">Armada</label><input name="fleet_name" value="{{ $booking->fleet_name }}" class="{{ $cls }}" placeholder="Bandung Central Fleet #04"></div>
                <div><label class="text-[12px] font-semibold">Driver / kendaraan</label><input name="driver_name" value="{{ $booking->driver_name }}" class="{{ $cls }}"></div>
                <div><label class="text-[12px] font-semibold">Titik bongkar</label><input name="delivery_point" value="{{ $booking->delivery_point }}" class="{{ $cls }}" placeholder="Dock B Rear Gate"></div>
                <div class="sm:col-span-3"><label class="text-[12px] font-semibold">Catatan crew (tampil di My Bookings)</label><input name="crew_note" value="{{ $booking->crew_note }}" class="{{ $cls }}" placeholder="Crew: Andi Prasetyo (Lead FOH), 2 Stage Hands assigned"></div>
                <div class="sm:col-span-3"><label class="text-[12px] font-semibold">Instruksi site clearance</label><textarea name="site_instructions" rows="2" class="w-full px-3 py-2 rounded-xl border border-line text-sm mt-1">{{ $booking->site_instructions }}</textarea></div>
            </div>

            <h3 class="text-[12px] uppercase tracking-wider text-muted font-semibold mt-6">Keuangan</h3>
            <div class="grid sm:grid-cols-3 gap-4 mt-2">
                <div><label class="text-[12px] font-semibold">Diskon (Rp)</label><input name="discount" value="{{ number_format($booking->discount, 0, ',', '.') }}" class="{{ $cls }}"></div>
                <div><label class="text-[12px] font-semibold">DP (%)</label><input type="number" name="dp_percent" value="{{ $booking->dp_percent }}" class="{{ $cls }}"></div>
                <div class="sm:col-span-3"><label class="text-[12px] font-semibold">Catatan customer</label><textarea name="notes" rows="2" class="w-full px-3 py-2 rounded-xl border border-line text-sm mt-1">{{ $booking->notes }}</textarea></div>
            </div>
            <button class="mt-6 h-11 px-8 rounded-full bg-ink text-white text-[13px] font-bold">Simpan Perubahan</button>
        </form>

        <div class="bg-white border border-line rounded-2xl p-6">
            <h2 class="font-semibold">Item Booking</h2>
            <table class="w-full text-[13px] mt-3">
                <thead class="text-left text-[11px] uppercase text-muted"><tr><th class="py-2">Item</th><th>Qty</th><th>Hari</th><th class="text-right">Rate</th><th class="text-right">Subtotal</th><th></th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($booking->items as $it)
                        <tr>
                            <td class="py-2"><b>{{ $it->name }}</b> <span class="text-[10px] uppercase bg-soft rounded px-1">{{ $it->item_type }}</span><div class="text-[11px] text-muted">{{ $it->description }}</div></td>
                            <td>{{ $it->qty }}</td><td>{{ $it->days }}</td>
                            <td class="text-right">{{ rupiah($it->rate) }}</td><td class="text-right font-semibold">{{ rupiah($it->subtotal) }}</td>
                            <td class="text-right"><form action="{{ route('admin.bookings.items.destroy', $it) }}" method="POST" onsubmit="return confirm('Hapus item?')">@csrf @method('DELETE')<button class="text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button></form></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <form action="{{ route('admin.bookings.items.store', $booking) }}" method="POST" class="grid grid-cols-2 sm:grid-cols-6 gap-2 mt-4 border-t border-line pt-4">@csrf
                <input name="name" placeholder="Nama item *" required class="{{ $cls }} col-span-2">
                <input name="description" placeholder="Keterangan" class="{{ $cls }} col-span-2">
                <input name="category" placeholder="Kategori (SOUND SYSTEM)" class="{{ $cls }} col-span-2">
                <select name="item_type" class="{{ $cls }} bg-white"><option value="product">product</option><option value="talent">talent</option><option value="crew">crew</option><option value="addon">addon</option><option value="service">service (logistik)</option></select>
                <input name="qty" type="number" value="1" min="1" class="{{ $cls }}" title="Qty">
                <input name="days" type="number" value="1" min="1" class="{{ $cls }}" title="Hari">
                <input name="rate" placeholder="Rate (Rp)" required class="{{ $cls }} col-span-2 sm:col-span-1">
                <button class="col-span-2 h-10 mt-1 rounded-full bg-ink text-white text-[12px] font-bold">+ Tambah</button>
            </form>
        </div>
    </div>

    <aside class="space-y-5">
        <div class="bg-white border border-line rounded-2xl p-5 text-[13px] space-y-1.5">
            <h2 class="font-semibold mb-2">Ringkasan</h2>
            <div class="flex justify-between"><span class="text-muted">Subtotal</span><span>{{ rupiah($booking->subtotal) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">Logistik</span><span>{{ rupiah($booking->logistics_fee) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">Diskon</span><span>- {{ rupiah($booking->discount) }}</span></div>
            <div class="flex justify-between font-bold text-[15px] border-t border-line pt-2"><span>Total</span><span>{{ rupiah($booking->total) }}</span></div>
            <div class="flex justify-between"><span class="text-muted">Dibayar</span><span class="text-emerald-600 font-semibold">{{ rupiah($booking->amount_paid) }} ({{ $booking->paid_percent }}%)</span></div>
            <div class="flex justify-between"><span class="text-muted">Sisa</span><b>{{ $booking->remaining ? rupiah($booking->remaining) : 'Lunas' }}</b></div>
            @if ($booking->overpaid)
                <div class="bg-red-50 text-red-700 rounded-lg px-3 py-2 text-[12px] mt-2">
                    Kelebihan bayar <b>{{ rupiah($booking->overpaid) }}</b>. Hapus pembayaran yang salah input di bawah, lalu catat ulang nominal yang benar.
                </div>
            @endif
        </div>

        <div class="bg-white border border-line rounded-2xl p-5">
            <h2 class="font-semibold">Pembayaran</h2>
            <div class="divide-y divide-line mt-2">
                @forelse ($booking->payments as $p)
                    <div class="py-3 text-[12.5px]">
                        <div class="flex items-center gap-2">
                            @if ($p->proof)<a href="{{ media_url($p->proof) }}" target="_blank"><img src="{{ media_url($p->proof) }}" class="w-10 h-10 rounded object-cover"></a>@endif
                            <div><b>{{ rupiah($p->amount) }}</b> · {{ $p->method }}<div class="text-[11px] text-muted">{{ $p->paid_at?->format('j M Y H:i') }} · ref {{ $p->reference ?: '-' }}</div></div>
                            <span class="ml-auto text-[10px] font-bold uppercase {{ ['verified' => 'text-emerald-600', 'pending' => 'text-amber-600', 'rejected' => 'text-red-600'][$p->status] }}">{{ $p->status }}</span>
                            <form action="{{ route('admin.payments.destroy', $p) }}" method="POST" onsubmit="return confirm('Hapus pembayaran {{ rupiah($p->amount) }}? Jumlah dibayar akan dikurangi.')">@csrf @method('DELETE')
                                <button class="text-red-600" title="Hapus pembayaran"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                            </form>
                        </div>
                        @if ($p->status === 'pending')
                            <form action="{{ route('admin.payments.verify', $p) }}" method="POST" class="flex gap-2 mt-2">@csrf
                                <button name="action" value="verify" class="h-8 px-3 rounded-full bg-emerald-600 text-white text-[12px] font-semibold">Verifikasi</button>
                                <button name="action" value="reject" class="h-8 px-3 rounded-full bg-red-50 text-red-600 text-[12px] font-semibold">Tolak</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="text-muted text-sm py-3">Belum ada pembayaran.</div>
                @endforelse
            </div>
            @if ($booking->remaining > 0)
                <form action="{{ route('admin.bookings.payments.store', $booking) }}" method="POST" class="grid grid-cols-2 gap-2 mt-3 border-t border-line pt-3">@csrf
                    <div class="col-span-2 text-[11px] text-muted">Catat pembayaran manual (langsung terverifikasi) · maks. <b>{{ rupiah($booking->remaining) }}</b></div>
                    <input name="amount" value="{{ old('amount') }}" placeholder="Nominal (Rp)" required inputmode="numeric" class="{{ $cls }}"
                           oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')">
                    <input name="method" value="{{ old('method') }}" placeholder="Metode (Cash / Transfer)" required class="{{ $cls }}">
                    <input name="reference" placeholder="Referensi" class="{{ $cls }} col-span-2">
                    <div class="col-span-2 flex gap-2 text-[11px]">
                        @if ($booking->next_due < $booking->remaining)
                            <button type="button" onclick="this.form.amount.value = '{{ number_format($booking->next_due, 0, ',', '.') }}'" class="px-2.5 py-1 rounded-full bg-soft">Isi DP {{ rupiah($booking->next_due) }}</button>
                        @endif
                        <button type="button" onclick="this.form.amount.value = '{{ number_format($booking->remaining, 0, ',', '.') }}'" class="px-2.5 py-1 rounded-full bg-soft">Isi pelunasan {{ rupiah($booking->remaining) }}</button>
                    </div>
                    <button class="col-span-2 h-10 mt-1 rounded-full bg-ink text-white text-[12px] font-bold">Catat Pembayaran</button>
                </form>
            @else
                <div class="mt-3 border-t border-line pt-3 text-[12px] text-emerald-700 font-semibold flex items-center gap-1.5"><i data-lucide="circle-check" class="w-4 h-4"></i> Booking sudah lunas.</div>
            @endif
        </div>

        <div class="bg-white border border-line rounded-2xl p-5 text-[12.5px]">
            <h2 class="font-semibold mb-2">Riwayat Pipeline</h2>
            @foreach ($booking->logs as $log)
                <div class="flex justify-between py-1"><span>{{ \App\Models\Booking::STAGES[$log->stage]['label'] }}</span><span class="text-muted">{{ $log->occurred_at->format('j M, H:i') }}</span></div>
            @endforeach
        </div>
    </aside>
</div>
@endsection
