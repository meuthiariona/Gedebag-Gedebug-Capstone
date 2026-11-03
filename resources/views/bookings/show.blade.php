@extends('layouts.app')
@section('title', 'Booking #'.$booking->code)
@section('subbar')@endsection

@section('content')
@php
    $stages = \App\Models\Booking::STAGES;
    $lastPay = $booking->payments->where('status', 'verified')->first();
    $pendingPay = $booking->payments->where('status', 'pending');
    $manifest = $booking->items->where('item_type', '!=', 'service');
    $wa = 'https://wa.me/'.setting('whatsapp', '6281234567890').'?text='.urlencode('Halo Dispatch Lead, terkait booking #'.$booking->code);
@endphp
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-6">
    <div class="text-[11px] flex items-center gap-1.5 text-muted">
        <i data-lucide="home" class="w-3.5 h-3.5"></i><a href="{{ route('home') }}" class="hover:underline">Home</a> /
        <a href="{{ route('bookings.index') }}" class="hover:underline">My Bookings</a> / <span class="text-ink font-semibold">Booking #{{ $booking->code }}</span>
    </div>

    {{-- Header --}}
    <div class="border border-line rounded-2xl p-6 mt-4 flex flex-col lg:flex-row lg:items-center gap-5 bg-gradient-to-r from-white to-soft/60">
        <div>
            <div class="flex flex-wrap items-center gap-2">@include('partials.ui', ['ui' => 'badges'])</div>
            <h1 class="text-3xl font-semibold tracking-tight mt-2">{{ $booking->event_name }}</h1>
            <div class="text-[12px] text-muted mt-1 flex flex-wrap gap-2 items-center">
                <i data-lucide="calendar" class="w-3.5 h-3.5"></i> {{ $booking->start_date->format('j M Y') }} – {{ $booking->end_date->format('j M Y') }}
                • <i data-lucide="map-pin" class="w-3.5 h-3.5"></i> {{ $booking->venue_name }}
            </div>
        </div>
        <div class="lg:ml-auto flex flex-wrap gap-2">
            <a href="{{ route('bookings.invoice', $booking) }}" class="h-10 px-4 rounded-full bg-soft text-[12px] font-semibold flex items-center gap-2"><i data-lucide="receipt" class="w-4 h-4"></i> View Invoice</a>
            <a href="{{ route('bookings.invoice', [$booking, 'print' => 1]) }}" class="h-10 px-4 rounded-full bg-soft text-[12px] font-semibold flex items-center gap-2"><i data-lucide="download" class="w-4 h-4"></i> Download PDF</a>
            <a href="{{ $wa }}" target="_blank" class="h-10 px-4 rounded-full bg-ink text-white text-[12px] font-bold flex items-center gap-2"><i data-lucide="headset" class="w-4 h-4"></i> Speak with Dispatch Lead</a>
        </div>
    </div>

    {{-- Pipeline --}}
    <div class="border border-line rounded-2xl p-6 mt-5">
        <div class="flex justify-between items-start">
            <div><div class="eyebrow text-muted">Realtime Operations Status</div><h2 class="text-lg font-semibold mt-1">Fulfillment & Rigging Pipeline</h2></div>
            <span class="text-[10.5px] bg-soft rounded-full px-3 py-1 flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Live Telemetry Synchronized</span>
        </div>
        <div class="overflow-x-auto scrollbar-none">
            <div class="relative grid grid-cols-7 min-w-[720px] mt-8">
                <div class="absolute top-[18px] left-[7%] right-[7%] h-0.5 bg-line"></div>
                <div class="absolute top-[18px] left-[7%] h-0.5 bg-ink" style="width: {{ 86 * $booking->stage / (count($stages) - 1) }}%"></div>
                @foreach ($stages as $i => $s)
                    @php($log = $booking->logFor($i))
                    <div class="relative text-center">
                        <div class="mx-auto grid place-items-center w-9 h-9 rounded-full border-2
                            {{ $i < $booking->stage ? 'bg-ink border-ink text-white' : ($i === $booking->stage ? 'bg-ink border-ink text-white ring-4 ring-ink/15' : 'bg-white border-line text-muted') }}">
                            <i data-lucide="{{ $i < $booking->stage || ($i === $booking->stage && $i < 3) ? 'check' : $s['icon'] }}" class="w-4 h-4"></i>
                        </div>
                        <div class="text-[11px] font-semibold mt-2">{{ $s['label'] }}</div>
                        <div class="text-[10px] text-muted">
                            @if ($log) {{ $log->occurred_at->format('j M, H:i') }}
                            @elseif ($i === 4) {{ $booking->start_date->format('j M') }} (est.)
                            @elseif ($i === 5) {{ $booking->end_date->format('j M') }} (est.)
                            @elseif ($i === 6) {{ $booking->end_date->copy()->addDay()->format('j M') }} (est.)
                            @else — @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="bg-soft rounded-xl px-4 py-3 mt-6 flex flex-wrap items-center gap-3">
            <span class="grid place-items-center w-8 h-8 rounded-full bg-white"><i data-lucide="activity" class="w-4 h-4"></i></span>
            <div>
                <div class="text-[12.5px] font-semibold">Current Phase: {{ $booking->phase_title ?: $stages[$booking->stage]['label'] }}</div>
                @if ($booking->phase_note)<div class="text-[11px] text-muted">{{ $booking->phase_note }}</div>@endif
            </div>
            @if ($booking->start_date->isFuture())
                <span class="ml-auto eyebrow bg-white rounded-full px-3 py-1">Estimated departure in {{ (int) now()->diffInHours($booking->start_date) }} hrs</span>
            @endif
        </div>
    </div>

    <div class="grid lg:grid-cols-[1fr_340px] gap-5 mt-5 items-start">
        <div class="space-y-5">
            {{-- Logistics --}}
            <div class="border border-line rounded-2xl p-6">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold flex items-center gap-2"><i data-lucide="git-fork" class="w-4 h-4"></i> Event & Venue Logistics</h2>
                    <span class="eyebrow bg-soft rounded-full px-3 py-1">{{ $booking->status === 'confirmed' ? 'Dispatch Verified' : \App\Models\Booking::STATUSES[$booking->status] }}</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-3 mt-5">
                    <div class="bg-soft rounded-xl p-4">
                        <div class="eyebrow text-muted flex items-center gap-1.5"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Rental Duration</div>
                        <div class="font-semibold mt-1.5">{{ $booking->start_date->format('j M Y') }} – {{ $booking->end_date->format('j M Y') }}</div>
                        <div class="text-[11px] text-muted">{{ $booking->days }} Full Operational Days</div>
                    </div>
                    <div class="bg-soft rounded-xl p-4">
                        <div class="eyebrow text-muted flex items-center gap-1.5"><i data-lucide="contact" class="w-3.5 h-3.5"></i> Client On-site PIC</div>
                        <div class="font-semibold mt-1.5">{{ $booking->pic_name }}</div>
                        <div class="text-[11px] text-muted flex items-center gap-1"><i data-lucide="phone" class="w-3 h-3"></i> {{ $booking->pic_phone }}</div>
                    </div>
                    <div class="bg-soft rounded-xl p-4 sm:col-span-2">
                        <div class="flex justify-between"><div class="eyebrow text-muted flex items-center gap-1.5"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> Venue / Delivery Destination</div><span class="text-[10.5px] text-muted">{{ $booking->venue_note }}</span></div>
                        <div class="font-semibold mt-1.5">{{ $booking->venue_name }}</div>
                        <div class="text-[11px] text-muted">{{ $booking->venue_address }}</div>
                        @if ($booking->venue_address)
                            <div class="relative rounded-lg overflow-hidden mt-3 h-44 bg-white">
                                <iframe class="w-full h-full" loading="lazy" src="https://maps.google.com/maps?q={{ urlencode($booking->venue_name.', '.$booking->venue_address) }}&z=14&output=embed"></iframe>
                                @if ($booking->delivery_point)<span class="absolute left-2 bottom-2 bg-white text-[10.5px] rounded-full px-2.5 py-1 shadow flex items-center gap-1"><i data-lucide="map-pin" class="w-3 h-3"></i> Delivery Point: {{ $booking->delivery_point }}</span>@endif
                            </div>
                        @endif
                    </div>
                    <div class="bg-soft rounded-xl p-4">
                        <div class="eyebrow text-muted flex items-center gap-1.5"><i data-lucide="truck" class="w-3.5 h-3.5"></i> Assigned Logistics Fleet</div>
                        <div class="font-semibold mt-1.5">{{ $booking->fleet_name ?: 'Menunggu penjadwalan' }}</div>
                        <div class="text-[11px] text-muted">{{ $booking->driver_name ? 'Driver: '.$booking->driver_name : 'Fleet ditetapkan setelah DP terkonfirmasi' }}</div>
                    </div>
                    <div class="bg-soft rounded-xl p-4">
                        <div class="eyebrow text-muted flex items-center gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5"></i> Site Clearance Instructions</div>
                        <div class="text-[11.5px] mt-1.5 whitespace-pre-line">{{ $booking->site_instructions ?: 'Belum ada instruksi khusus.' }}</div>
                    </div>
                </div>
            </div>

            {{-- Manifest --}}
            <div class="border border-line rounded-2xl p-6">
                <div class="flex justify-between items-center">
                    <div><div class="eyebrow text-muted">Hardware Manifest</div><h2 class="text-lg font-semibold mt-1">Reserved Equipment & Talent Roster</h2></div>
                    <span class="text-[11px] border border-line rounded-full px-3 py-1 flex items-center gap-1.5"><i data-lucide="list-checks" class="w-3.5 h-3.5"></i> {{ $manifest->count() }} Active Items Allocated</span>
                </div>
                <div class="space-y-3 mt-5">
                    @foreach ($manifest as $item)
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-4 border border-line rounded-xl p-3">
                            @if ($item->image_url)
                                <img src="{{ $item->image_url }}" alt="" class="w-14 h-14 rounded-lg object-cover bg-soft shrink-0">
                            @else
                                <span class="grid place-items-center w-14 h-14 rounded-lg bg-ink text-white shrink-0"><i data-lucide="audio-lines" class="w-5 h-5"></i></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-[9px] font-bold uppercase tracking-wider bg-soft rounded px-1.5 py-0.5">{{ $item->category ?: $item->item_type }}</span>
                                    <span class="text-[10px] text-muted">ID: {{ strtoupper(substr($item->item_type, 0, 3)).'-'.str_pad($item->item_id ?? $item->id, 4, '0', STR_PAD_LEFT) }}</span>
                                </div>
                                <div class="font-semibold text-[13.5px] mt-0.5">{{ $item->name }}</div>
                                <div class="text-[11px] text-muted line-clamp-1">{{ $item->description }}</div>
                            </div>
                            <div class="text-right"><div class="eyebrow text-muted">Quantity</div><div class="font-semibold text-[13px]">{{ $item->qty }} {{ $item->item_type === 'package' ? 'Set' : 'Units' }}</div></div>
                            <div class="text-right w-32"><div class="eyebrow text-muted">{{ $item->days > 1 ? 'Rate / '.$item->days.' Days' : 'Total Fee' }}</div><div class="font-semibold text-[13px]">{{ $item->subtotal ? rupiah($item->subtotal) : 'Included' }}</div></div>
                        </div>
                    @endforeach
                </div>
                <div class="flex justify-between text-[11px] text-muted mt-4">
                    <span class="flex items-center gap-1.5"><i data-lucide="scan-barcode" class="w-3.5 h-3.5"></i> All flight cases barcode serialized against manifest #MNF-{{ $booking->id }}</span>
                </div>
            </div>
        </div>

        {{-- Billing --}}
        <aside class="space-y-4">
            <div class="border border-line rounded-2xl p-5">
                <div class="flex justify-between items-center"><h2 class="font-semibold">Payment & Billing Overview</h2><i data-lucide="wallet" class="w-4 h-4 text-muted"></i></div>
                <div class="text-[12px] space-y-2 mt-4">
                    <div class="flex justify-between"><span class="text-muted">Rental & Talent Subtotal</span><span>{{ rupiah($booking->subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Logistics & Delivery Fee</span><span>{{ rupiah($booking->logistics_fee) }}</span></div>
                    @if ($booking->discount)<div class="flex justify-between"><span class="text-muted">Discount</span><span class="text-red-600">- {{ rupiah($booking->discount) }}</span></div>@endif
                    <div class="flex justify-between font-semibold text-[13px] pt-1"><span>Total Booking Amount</span><span>{{ rupiah($booking->total) }}</span></div>
                </div>
                <div class="bg-soft rounded-xl p-3.5 mt-4 space-y-3">
                    <div class="flex justify-between items-start">
                        <div><div class="text-[10.5px] font-semibold text-emerald-600 flex items-center gap-1"><i data-lucide="circle-check" class="w-3.5 h-3.5"></i> Amount Paid ({{ $booking->paid_percent }}%)</div>
                            @if ($lastPay)<div class="text-[10px] text-muted">Via {{ $lastPay->method }} • {{ $lastPay->paid_at?->format('j M Y') }}</div>@endif</div>
                        <b>{{ rupiah($booking->amount_paid) }}</b>
                    </div>
                    <div class="flex justify-between items-start border-t border-line pt-3">
                        <div><div class="eyebrow">Remaining Balance</div><div class="text-[10px] text-muted">Due prior to event handover</div>
                            @if ($booking->remaining)<div class="text-[10px] text-red-600 mt-1 flex items-center gap-1"><i data-lucide="alarm-clock" class="w-3 h-3"></i> Due: {{ $booking->start_date->format('j M Y') }}</div>@endif</div>
                        <b class="text-lg">{{ rupiah($booking->remaining) }}</b>
                    </div>
                </div>
                @foreach ($pendingPay as $p)
                    <div class="mt-3 text-[11px] bg-amber-50 text-amber-800 rounded-lg px-3 py-2">Pembayaran {{ rupiah($p->amount) }} ({{ $p->method }}) sedang diverifikasi.</div>
                @endforeach
                @if ($booking->remaining > 0 && $booking->status !== 'cancelled')
                    <a href="{{ route('orders.whatsapp', $booking->access_token) }}" target="_blank" rel="noopener" class="mt-4 w-full h-10 rounded-full bg-emerald-600 text-white text-[12px] font-bold flex items-center justify-center gap-2">
                        <i data-lucide="message-circle" class="w-4 h-4"></i> {{ $booking->payment_status === 'unpaid' ? 'Negosiasi & Bayar via WhatsApp' : 'Pelunasan via WhatsApp ('.rupiah($booking->remaining).')' }}</a>
                @endif
                <a href="{{ route('bookings.invoice', $booking) }}" class="mt-2 block text-center text-[11.5px] font-semibold hover:underline">View Full Tax Invoice #{{ $booking->invoice_no }}</a>
                <div class="flex justify-between text-[10px] text-muted mt-4"><span class="flex items-center gap-1"><i data-lucide="lock" class="w-3 h-3"></i> 256-Bit Escrow</span><span class="flex items-center gap-1"><i data-lucide="shield-check" class="w-3 h-3"></i> Official NPWP Validated</span></div>
            </div>

            <div class="rounded-2xl bg-ink text-white p-5 relative overflow-hidden">
                <div class="absolute -right-10 -bottom-10 w-32 h-32 rounded-full bg-white/5"></div>
                <h3 class="font-semibold flex items-center gap-2"><i data-lucide="shield" class="w-4 h-4"></i> {{ setting('site_name', 'NEXA') }} Hardware Guarantee</h3>
                <div class="text-[11px] text-white/75 mt-2 whitespace-pre-line leading-relaxed">{{ setting('sla_text') }}</div>
                <div class="flex justify-between text-[10px] text-white/60 border-t border-white/10 mt-4 pt-3"><span>Policy: {{ $booking->code }}-SLA</span></div>
            </div>

            <a href="tel:{{ preg_replace('/[^\d+]/', '', setting('support_phone', '')) }}" class="border border-line rounded-2xl p-4 flex items-center gap-3">
                <i data-lucide="asterisk" class="w-5 h-5"></i>
                <div><div class="text-[13px] font-semibold">Operations Hotline</div><div class="text-[11px] text-muted">Live radio dispatch (24h)</div></div>
                <span class="ml-auto grid place-items-center w-8 h-8 rounded-full bg-soft"><i data-lucide="phone-call" class="w-4 h-4"></i></span>
            </a>
        </aside>
    </div>
</div>
@endsection
