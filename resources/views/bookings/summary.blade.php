@extends('layouts.app')
@section('title', 'Ringkasan Pesanan #'.$booking->code)

@section('content')
@php
    $bar = 'dispatch';
    $orderItems = $booking->items->where('item_type', '!=', 'service');
    $services = $booking->items->where('item_type', 'service');
    $message = $booking->whatsappMessage();
@endphp
<div class="max-w-[1100px] mx-auto px-4 sm:px-6 pt-10" x-data="{ copied: '' }">
    <div class="flex items-start gap-4">
        <span class="grid place-items-center w-12 h-12 rounded-full bg-emerald-50 text-emerald-600 shrink-0"><i data-lucide="circle-check" class="w-6 h-6"></i></span>
        <div>
            <div class="eyebrow text-muted">Pesanan #{{ $booking->code }}</div>
            <h1 class="text-3xl font-semibold tracking-tight mt-1">Ringkasan Pesanan</h1>
            <p class="text-[13px] text-muted mt-1">Pesanan sudah tersimpan. Langkah terakhir: kirim detail ini ke WhatsApp tim kami untuk negosiasi & kesepakatan harga.</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-[1fr_380px] gap-6 mt-8 items-start">
        <div class="space-y-5">
            {{-- Data pemesan & event --}}
            <div class="border border-line rounded-2xl p-6 grid sm:grid-cols-2 gap-6 text-[12.5px]">
                <div>
                    <div class="eyebrow text-muted mb-2">Data Pemesan</div>
                    <div class="font-semibold text-[14px]">{{ $booking->customer_name }}</div>
                    @if ($booking->customer_company)<div>{{ $booking->customer_company }}</div>@endif
                    <div class="text-muted mt-1 flex items-center gap-1.5"><i data-lucide="phone" class="w-3.5 h-3.5"></i> {{ $booking->customer_phone }}</div>
                    @if ($booking->customer_email)<div class="text-muted flex items-center gap-1.5"><i data-lucide="mail" class="w-3.5 h-3.5"></i> {{ $booking->customer_email }}</div>@endif
                </div>
                <div>
                    <div class="eyebrow text-muted mb-2">Detail Event</div>
                    <div class="font-semibold text-[14px]">{{ $booking->event_name }}</div>
                    <div class="text-muted flex items-center gap-1.5 mt-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> {{ $booking->start_date->format('j M Y') }} – {{ $booking->end_date->format('j M Y') }} ({{ $booking->days }} hari)</div>
                    <div class="text-muted flex items-center gap-1.5"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> {{ $booking->venue_name }}@if($booking->venue_note) · {{ $booking->venue_note }}@endif</div>
                    <div class="text-muted pl-5">{{ $booking->venue_address }}</div>
                    @if ($booking->expected_pax)<div class="text-muted flex items-center gap-1.5"><i data-lucide="users" class="w-3.5 h-3.5"></i> {{ $booking->expected_pax }} pax</div>@endif
                    <div class="text-muted flex items-center gap-1.5"><i data-lucide="contact" class="w-3.5 h-3.5"></i> PIC: {{ $booking->pic_name }} ({{ $booking->pic_phone }})</div>
                </div>
                @if ($booking->notes)
                    <div class="sm:col-span-2 bg-soft rounded-xl px-4 py-3"><b>Catatan:</b> {{ $booking->notes }}</div>
                @endif
            </div>

            {{-- Daftar pesanan --}}
            <div class="border border-line rounded-2xl p-6">
                <h2 class="font-semibold">Yang Dipesan</h2>
                <div class="divide-y divide-line mt-3">
                    @foreach ($orderItems as $item)
                        <div class="py-3 flex items-center gap-4 {{ $item->item_type === 'addon' ? 'pl-6' : '' }}">
                            @if ($item->item_type !== 'addon')
                                <img src="{{ $item->image_url ?? media_url(null) }}" alt="" class="w-14 h-14 rounded-lg object-cover bg-soft shrink-0">
                            @endif
                            <div class="min-w-0 flex-1">
                                <span class="text-[9px] font-bold uppercase tracking-wider bg-soft rounded px-1.5 py-0.5">{{ $item->category ?: $item->item_type }}</span>
                                <div class="font-semibold text-[13.5px] mt-0.5">{{ $item->item_type === 'addon' ? '+ ' : '' }}{{ $item->name }}</div>
                                <div class="text-[11px] text-muted line-clamp-1">{{ $item->description }}</div>
                            </div>
                            <div class="text-right text-[12px] shrink-0">
                                <div class="text-muted">{{ $item->qty }}×{{ $item->unit === 'hari' && $item->days > 1 ? ' · '.$item->days.' hari' : '' }}</div>
                                <div class="font-semibold">{{ rupiah($item->subtotal) }}</div>
                            </div>
                        </div>
                    @endforeach
                    @foreach ($services as $item)
                        <div class="py-3 flex justify-between text-[12.5px]"><span class="text-muted">{{ $item->name }}</span><span>{{ rupiah($item->subtotal) }}</span></div>
                    @endforeach
                </div>
                <div class="flex justify-between items-end border-t border-line pt-4 mt-1">
                    <div><div class="font-semibold">Estimasi Total</div><div class="text-[11px] text-muted">Harga final mengikuti hasil negosiasi</div></div>
                    <div class="text-2xl font-bold">{{ rupiah($booking->total) }}</div>
                </div>
            </div>
        </div>

        {{-- WhatsApp --}}
        <aside class="lg:sticky lg:top-24 space-y-4">
            <div class="border border-line rounded-2xl p-5 shadow-card">
                <div class="flex items-center gap-2">
                    <span class="grid place-items-center w-9 h-9 rounded-full bg-emerald-600 text-white"><i data-lucide="message-circle" class="w-4 h-4"></i></span>
                    <div><div class="font-semibold">Negosiasi via WhatsApp</div><div class="text-[11px] text-muted">Pesan sudah terisi otomatis sesuai pesanan</div></div>
                </div>
                <pre class="mt-4 bg-soft rounded-xl p-3 text-[11px] leading-relaxed whitespace-pre-wrap [overflow-wrap:anywhere] font-sans max-h-72 overflow-y-auto overflow-x-hidden">{{ $message }}</pre>
                {{-- Buka WA di tab baru; tab ini beralih ke halaman detail booking (status negosiasi) --}}
                <a href="{{ route('orders.whatsapp', $booking->access_token) }}" target="_blank" rel="noopener"
                   @click="setTimeout(() => window.location = @js(route('bookings.show', $booking)), 800)"
                   class="mt-4 w-full h-11 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white text-[13px] font-bold flex items-center justify-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i> Chat via WhatsApp
                </a>
                <button type="button" @click="navigator.clipboard.writeText(@js($message)); copied = 'msg'; setTimeout(() => copied = '', 2000)"
                        class="mt-2 w-full h-10 rounded-full bg-soft text-[12px] font-semibold flex items-center justify-center gap-2">
                    <i data-lucide="copy" class="w-4 h-4"></i> <span x-text="copied === 'msg' ? 'Pesan disalin!' : 'Salin teks pesan'">Salin teks pesan</span>
                </button>
            </div>

            <div class="bg-soft rounded-2xl p-4 text-[11.5px]">
                <div class="font-semibold flex items-center gap-1.5"><i data-lucide="bookmark" class="w-3.5 h-3.5"></i> Simpan link ringkasan ini</div>
                <p class="text-muted mt-1">Pesanan ini juga tersimpan di <a href="{{ route('bookings.index') }}" class="font-semibold text-ink underline">My Bookings</a> pada browser ini. Link di bawah ikut terkirim di chat WhatsApp, jadi bisa dibuka dari perangkat lain.</p>
                <button type="button" @click="navigator.clipboard.writeText(@js($booking->summary_url)); copied = 'link'; setTimeout(() => copied = '', 2000)"
                        class="mt-2 font-semibold underline" x-text="copied === 'link' ? 'Link disalin!' : 'Salin link'">Salin link</button>
            </div>
        </aside>
    </div>
</div>
@endsection
