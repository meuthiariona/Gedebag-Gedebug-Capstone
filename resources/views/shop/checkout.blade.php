@extends('layouts.app')
@section('title', 'Checkout')

@section('content')
@php
    [$rs, $re, $days] = rental_dates();
    $u = auth()->user(); // opsional: checkout tidak wajib login, data hanya dipakai untuk isian awal
    $input = 'w-full h-11 px-4 rounded-xl border border-line bg-white text-sm outline-none focus:border-ink';
@endphp
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-10">
    <div class="eyebrow flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-ink"></span> Checkout</div>
    <h1 class="text-3xl font-semibold tracking-tight mt-2">Data Pemesan & Detail Event</h1>
    <p class="text-[13px] text-muted mt-1">Tanpa perlu login. Setelah dikirim, harga final dinegosiasikan langsung dengan tim kami lewat WhatsApp.</p>

    <form action="{{ route('checkout') }}" method="POST" class="grid lg:grid-cols-[1fr_360px] gap-8 mt-8 items-start">
        @csrf
        <div class="space-y-5">
            @if ($errors->any())
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 text-sm">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Data diri --}}
            <div class="border border-line rounded-2xl p-6 space-y-4">
                <h2 class="font-semibold flex items-center gap-2"><i data-lucide="user-round" class="w-4 h-4"></i> Data Diri</h2>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[12px] font-semibold">Nama Lengkap *</label>
                        <input name="customer_name" value="{{ old('customer_name', $u?->name) }}" required class="{{ $input }} mt-1" placeholder="Yoas Immanuel">
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold">No. WhatsApp *</label>
                        <input name="customer_phone" type="tel" value="{{ old('customer_phone', $u?->phone) }}" required class="{{ $input }} mt-1" placeholder="0812-3456-7890">
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold">Email</label>
                        <input name="customer_email" type="email" value="{{ old('customer_email', $u?->email) }}" class="{{ $input }} mt-1" placeholder="nama@email.com">
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold">Perusahaan / Instansi</label>
                        <input name="customer_company" value="{{ old('customer_company', $u?->company) }}" class="{{ $input }} mt-1" placeholder="Opsional">
                    </div>
                </div>
            </div>

            {{-- Detail event --}}
            <div class="border border-line rounded-2xl p-6 space-y-4">
                <h2 class="font-semibold flex items-center gap-2"><i data-lucide="calendar-range" class="w-4 h-4"></i> Detail Event & Venue</h2>
                <div>
                    <label class="text-[12px] font-semibold">Nama Event *</label>
                    <input name="event_name" value="{{ old('event_name') }}" required class="{{ $input }} mt-1" placeholder="Corporate Gathering & Product Launch">
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-[12px] font-semibold">Nama Venue *</label>
                        <input name="venue_name" value="{{ old('venue_name') }}" required class="{{ $input }} mt-1" placeholder="Graha Telkom Convention Hall">
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold">Area / Ruangan</label>
                        <input name="venue_note" value="{{ old('venue_note') }}" class="{{ $input }} mt-1" placeholder="Grand Ballroom Main Rig">
                    </div>
                </div>
                <div>
                    <label class="text-[12px] font-semibold">Alamat Venue *</label>
                    <input name="venue_address" value="{{ old('venue_address') }}" required class="{{ $input }} mt-1" placeholder="Jl. Japati No. 1, Bandung">
                </div>
                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="text-[12px] font-semibold">Perkiraan Tamu (pax)</label>
                        <input type="number" min="1" name="expected_pax" value="{{ old('expected_pax') }}" class="{{ $input }} mt-1" placeholder="450">
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold">PIC di Lokasi *</label>
                        <input name="pic_name" value="{{ old('pic_name', $u?->name) }}" required class="{{ $input }} mt-1">
                    </div>
                    <div>
                        <label class="text-[12px] font-semibold">No. HP PIC *</label>
                        <input name="pic_phone" value="{{ old('pic_phone', $u?->phone) }}" required class="{{ $input }} mt-1">
                    </div>
                </div>
                <div>
                    <label class="text-[12px] font-semibold">Catatan / Rider khusus</label>
                    <textarea name="notes" rows="3" class="w-full px-4 py-3 rounded-xl border border-line text-sm outline-none focus:border-ink mt-1">{{ old('notes') }}</textarea>
                </div>
            </div>
        </div>

        <aside class="border border-line rounded-2xl p-5 shadow-card lg:sticky lg:top-24">
            <div class="eyebrow text-muted">Ringkasan Pesanan</div>
            <div class="bg-soft rounded-xl p-3 mt-3 text-[11.5px] flex justify-between"><span class="text-muted">Periode</span><b>{{ $rs->format('j M') }} – {{ $re->format('j M Y') }} ({{ $days }} hari)</b></div>
            <div class="divide-y divide-line mt-3">
                @foreach ($lines as $line)
                    <div class="py-2 flex justify-between gap-3 text-[12px]">
                        <span>{{ $line['qty'] }}× {{ $line['name'] }}@if($line['addons']->isNotEmpty())<span class="text-muted"> +{{ $line['addons']->count() }} add-on</span>@endif</span>
                        <span class="shrink-0">{{ rupiah($line['subtotal']) }}</span>
                    </div>
                @endforeach
                <div class="py-2 flex justify-between text-[12px]"><span class="text-muted">Logistics & delivery</span><span>{{ rupiah($totals['logistics']) }}</span></div>
            </div>
            <div class="flex justify-between items-end border-t border-line mt-2 pt-4">
                <span class="font-semibold">Estimasi Total</span><span class="text-xl font-bold">{{ rupiah($totals['total']) }}</span>
            </div>
            <p class="text-[11px] text-muted mt-2">Harga di atas masih estimasi. Harga final & pembayaran disepakati lewat WhatsApp. Jadwal di-hold {{ setting('hold_hours', 48) }} jam.</p>
            <button class="mt-5 w-full h-11 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-bold flex items-center justify-center gap-2">
                <i data-lucide="message-circle" class="w-4 h-4"></i> Kirim Pesanan & Negosiasi via WA
            </button>
        </aside>
    </form>
</div>
@endsection
