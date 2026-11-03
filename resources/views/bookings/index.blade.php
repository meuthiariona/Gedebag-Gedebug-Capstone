@extends('layouts.app')
@section('title', 'My Bookings')
@php($bar = 'dispatch')

@section('content')
@php($u = auth()->user())
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-10">
    <div class="flex flex-col md:flex-row md:items-end gap-6">
        <div>
            @if ($u?->company)<span class="inline-block bg-soft rounded px-2 py-0.5 eyebrow">Corporate Account / {{ $u->company }}</span>@endif
            <h1 class="text-3xl font-semibold tracking-tight mt-2">My Bookings</h1>
            <p class="text-[13px] text-muted mt-1">Track active hardware dispatches, crew schedules, and download invoices for your upcoming events.</p>
            @guest
                <p class="text-[12px] text-muted mt-2 flex items-center gap-1.5"><i data-lucide="info" class="w-3.5 h-3.5"></i>
                    Menampilkan pesanan yang dibuat dari browser ini. <a href="{{ route('login') }}" class="font-semibold text-ink underline">Login</a> agar pesanan tersimpan di akun & bisa dibuka dari perangkat lain.</p>
            @endguest
        </div>
        <div class="md:ml-auto grid grid-cols-3 gap-8 border border-line rounded-2xl px-6 py-4">
            <div><div class="eyebrow text-muted">Total Reserved</div><div class="font-bold mt-1">{{ $summary['reserved'] }} Events</div></div>
            <div><div class="eyebrow text-muted">Next Deployment</div><div class="font-bold mt-1">{{ $summary['next']?->format('j M Y') ?? '-' }}</div></div>
            <div><div class="eyebrow text-muted">Pending DP</div><div class="font-bold mt-1">{{ $summary['pending'] >= 1000000 ? 'Rp '.round($summary['pending'] / 1000000, 1).'M' : rupiah($summary['pending']) }}</div></div>
        </div>
    </div>

    <form method="GET" class="flex flex-wrap items-center gap-3 mt-8">
        <div class="flex bg-soft rounded-full p-1 text-[12px] font-semibold">
            @foreach (['upcoming' => 'Upcoming', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $label)
                <a href="{{ route('bookings.index', ['tab' => $k]) }}" class="px-4 py-1.5 rounded-full flex items-center gap-2 {{ $tab === $k ? 'bg-ink text-white' : '' }}">
                    {{ $label }} <span class="{{ $tab === $k ? 'bg-white text-ink' : 'text-muted' }} rounded-full px-1.5 text-[10px]">{{ $counts[$k] }}</span>
                </a>
            @endforeach
        </div>
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="ml-auto flex items-center gap-2 border border-line rounded-full px-4 h-10 w-full sm:w-72">
            <i data-lucide="search" class="w-4 h-4 text-muted"></i>
            <input name="q" value="{{ $search }}" placeholder="Search by Booking ID or Event Name..." class="text-[12px] outline-none w-full">
        </div>
        <select name="month" onchange="this.form.submit()" class="h-10 border border-line rounded-full px-4 text-[12px] font-semibold bg-white">
            <option value="">Semua bulan</option>
            @foreach ($months as $m)
                <option value="{{ $m }}" @selected($month === $m)>{{ \Illuminate\Support\Carbon::parse($m.'-01')->format('M Y') }}</option>
            @endforeach
        </select>
    </form>

    <div class="space-y-5 mt-6">
        @forelse ($bookings as $booking)
            @php($alloc = $booking->items->whereNotIn('item_type', ['service']))
            <div class="border border-line rounded-2xl p-5 sm:p-6 grid lg:grid-cols-[1fr_260px] gap-6 {{ $booking->status === 'confirmed' && $booking->payment_status !== 'paid' ? 'border-l-4 border-l-ink' : '' }}">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        @include('partials.ui', ['ui' => 'badges'])
                        @if ($booking->status === 'pending' && $booking->hold_expires_at)
                            <span class="text-[10.5px] text-muted flex items-center gap-1"><i data-lucide="timer" class="w-3.5 h-3.5"></i>
                                {{ $booking->hold_expires_at->isFuture() ? 'Reservation hold: '.(int) now()->diffInHours($booking->hold_expires_at).'h left' : 'Hold expired' }}</span>
                        @elseif ($booking->inspection_label)
                            <span class="text-[10.5px] text-muted flex items-center gap-1"><i data-lucide="clipboard-check" class="w-3.5 h-3.5"></i> {{ $booking->inspection_label }}</span>
                        @endif
                    </div>
                    <h2 class="text-xl font-semibold tracking-tight mt-3">{{ $booking->event_name }}</h2>
                    <div class="flex flex-wrap gap-x-5 gap-y-1 text-[11.5px] text-muted mt-1">
                        <span class="flex items-center gap-1"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> {{ $booking->date_range }} ({{ $booking->days }} Days)</span>
                        <span class="flex items-center gap-1"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> {{ $booking->venue_name }}</span>
                        @if ($booking->expected_pax)<span class="flex items-center gap-1"><i data-lucide="users" class="w-3.5 h-3.5"></i> Expected {{ $booking->expected_pax }} Pax</span>
                        @elseif ($booking->venue_note)<span class="flex items-center gap-1"><i data-lucide="landmark" class="w-3.5 h-3.5"></i> {{ $booking->venue_note }}</span>@endif
                    </div>
                    <div class="bg-soft rounded-xl p-4 mt-4">
                        <div class="flex justify-between eyebrow text-muted">
                            <span class="flex items-center gap-1.5"><i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i> Staged Equipment & Talent Allocation</span>
                            <span>{{ $alloc->count() }} Allocated Sets</span>
                        </div>
                        <div class="text-[13px] mt-2">{{ $alloc->map(fn ($i) => $i->name.($i->qty > 1 ? ' (x'.$i->qty.')' : ''))->implode(', ') }}</div>
                        @if ($booking->crew_note)<div class="text-[11px] text-muted mt-2">{{ $booking->crew_note }}</div>@endif
                        @if ($booking->status === 'pending')<div class="text-[11px] text-muted mt-2">Hold placement active. Hardware reserved until checkout completion.</div>@endif
                    </div>
                </div>

                <div class="bg-soft/60 rounded-xl p-4">
                    <div class="flex justify-between eyebrow"><span>Settlement Progress</span><span>{{ $booking->paid_percent }}% {{ $booking->paid_percent >= 100 ? 'Settled' : 'Paid' }}</span></div>
                    <div class="h-1.5 bg-line rounded-full mt-2 overflow-hidden"><div class="h-full bg-ink rounded-full" style="width: {{ $booking->paid_percent }}%"></div></div>
                    <div class="text-[12px] space-y-1.5 mt-4">
                        <div class="flex justify-between"><span class="text-muted">Grand Total</span><b>{{ rupiah($booking->total) }}</b></div>
                        <div class="flex justify-between"><span class="text-muted">Paid</span><span>{{ rupiah($booking->amount_paid) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">{{ $booking->payment_status === 'unpaid' ? 'Deposit Required ('.$booking->dp_percent.'%)' : 'Remaining Balance' }}</span>
                            <b>{{ $booking->payment_status === 'unpaid' ? rupiah($booking->dp_amount) : rupiah($booking->remaining).($booking->remaining === 0 ? ' (Lunas)' : '') }}</b></div>
                    </div>
                    <div class="text-[10.5px] text-muted mt-2">
                        @if ($booking->payment_status === 'paid') Official tax receipt #{{ $booking->invoice_no }} issued
                        @elseif ($booking->payment_status === 'dp_paid') Due on final technical load-in ({{ $booking->start_date->format('j M') }})
                        @else Harga final & pembayaran disepakati via WhatsApp @endif
                    </div>
                    <div class="mt-4 space-y-2">
                        @if ($booking->payment_status === 'unpaid' && $booking->status === 'pending')
                            <a href="{{ route('orders.whatsapp', $booking->access_token) }}" target="_blank" rel="noopener" class="w-full h-10 rounded-full bg-emerald-600 text-white text-[12px] font-bold flex items-center justify-center gap-2"><i data-lucide="message-circle" class="w-4 h-4"></i> Negosiasi via WhatsApp</a>
                            <a href="{{ route('bookings.show', $booking) }}" class="w-full h-9 rounded-full bg-white text-[12px] font-semibold flex items-center justify-center gap-1">View Details <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
                        @else
                            <a href="{{ route('bookings.show', $booking) }}" class="w-full h-10 rounded-full bg-ink text-white text-[12px] font-bold flex items-center justify-center gap-2">View Booking Details <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
                            <div class="grid grid-cols-2 gap-2">
                                <a href="{{ route('bookings.invoice', $booking) }}" class="h-9 rounded-full bg-white text-[11.5px] font-semibold flex items-center justify-center gap-1"><i data-lucide="file-text" class="w-3.5 h-3.5"></i> Invoice</a>
                                <a href="https://wa.me/{{ setting('whatsapp', '6281234567890') }}?text={{ urlencode('Halo Lead Engineer, terkait booking #'.$booking->code) }}" target="_blank" class="h-9 rounded-full bg-white text-[11.5px] font-semibold flex items-center justify-center gap-1"><i data-lucide="headset" class="w-3.5 h-3.5"></i> Lead Eng.</a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-16 border border-dashed border-line rounded-2xl text-muted">
                Belum ada booking di tab ini. <a href="{{ route('packages') }}" class="font-semibold text-ink underline">Lihat paket</a>
            </div>
        @endforelse
    </div>

    {{-- Dispatch control --}}
    <div class="border border-line rounded-2xl p-6 mt-8 grid md:grid-cols-[1fr_1.3fr] gap-6 items-center">
        <div>
            <div class="eyebrow text-muted">Live Technical Logistics</div>
            <h3 class="text-xl font-semibold mt-1">Regional Hardware Dispatch Control</h3>
            <p class="text-[12px] text-muted mt-2">All active bookings are coordinated straight through our central warehouse in {{ setting('ops_center_address', 'Bandung') }}. Trucks load out 6 hours prior to stage call.</p>
            <div class="text-[11.5px] space-y-1.5 mt-4">
                <div class="flex items-center gap-2"><i data-lucide="truck" class="w-4 h-4"></i> Isuzu Giga Fleet staging for venue prep</div>
                <div class="flex items-center gap-2"><i data-lucide="shield-check" class="w-4 h-4"></i> Complimentary electrical surge protection installed</div>
            </div>
        </div>
        <div class="relative rounded-xl overflow-hidden bg-soft h-56">
            <iframe class="w-full h-full grayscale" loading="lazy" src="https://maps.google.com/maps?q={{ urlencode(setting('ops_center_address', 'Bandung')) }}&z=13&output=embed"></iframe>
            <div class="absolute left-3 bottom-3 bg-white rounded-lg shadow px-3 py-2 text-[11px] flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-ink"></span>
                <div><b>{{ setting('ops_center_name', 'Operations Center') }}</b><div class="text-muted text-[10px]">{{ setting('ops_center_address') }}</div></div>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-2xl bg-ink text-white px-6 sm:px-8 py-8 flex flex-col lg:flex-row lg:items-center gap-5">
        <div>
            <span class="inline-flex items-center gap-1.5 bg-white/10 rounded-full px-3 py-1 eyebrow"><i data-lucide="headphones" class="w-3 h-3"></i> Dedicated Dispatch Desk</span>
            <h3 class="text-xl font-bold mt-3">Need to reschedule or update stage technical rider?</h3>
            <p class="text-white/70 text-[12.5px] mt-1 max-w-xl">Our operations team is available 24/7 to adjust audio configurations, engineer rosters, or venue access permits.</p>
        </div>
        <div class="lg:ml-auto flex flex-wrap gap-3">
            <a href="tel:{{ preg_replace('/[^\d+]/', '', setting('support_phone', '')) }}" class="h-11 px-6 rounded-full bg-white text-ink text-[12px] font-bold flex items-center gap-2"><i data-lucide="phone" class="w-4 h-4"></i> {{ setting('support_phone') }}</a>
            <a href="https://wa.me/{{ setting('whatsapp', '6281234567890') }}" target="_blank" class="h-11 px-6 rounded-full bg-white/15 text-[12px] font-bold flex items-center gap-2"><i data-lucide="message-square" class="w-4 h-4"></i> Live Chat Ops</a>
        </div>
    </div>
</div>
@endsection
