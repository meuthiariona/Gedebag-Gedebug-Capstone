@extends('layouts.app')
@section('title', 'Kalender Booking')

@section('content')
@php
    $statusLabel = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed'];
@endphp

<div class="flex flex-wrap items-center gap-3 mb-4">
    @include('admin.bookings._tabs')

    <div class="ml-auto flex flex-wrap items-center gap-2">
        @if ($configured)
            <span class="h-9 px-3 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-[12px] font-semibold flex items-center gap-1.5" title="{{ $calendarId }}">
                <i data-lucide="calendar-check-2" class="w-3.5 h-3.5"></i> Terhubung ke Google Calendar
            </span>
            @can('bookings.manage')
                <form action="{{ route('admin.bookings.calendar.sync') }}" method="POST">@csrf
                    <button class="h-9 px-4 rounded-full bg-ink text-white text-[12px] font-semibold flex items-center gap-1.5">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Sinkronkan sekarang
                        @if ($pendingCount)<span class="ml-1 px-1.5 rounded-full bg-white/20 text-[11px]">{{ $pendingCount }}</span>@endif
                    </button>
                </form>
            @endcan
        @else
            <span class="h-9 px-3 rounded-full bg-soft text-muted text-[12px] font-semibold flex items-center gap-1.5">
                <i data-lucide="calendar-x-2" class="w-3.5 h-3.5"></i> Google Calendar belum terhubung
            </span>
        @endif
    </div>
</div>

@if ($googleError)
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 text-sm mb-4">{{ $googleError }} Booking dari sistem tetap tampil.</div>
@endif
@if ($failedCount)
    <div class="bg-amber-50 text-amber-800 border border-amber-200 rounded-xl px-4 py-3 text-sm mb-4">
        {{ $failedCount }} booking gagal dikirim ke Google Calendar. Sistem mencoba ulang otomatis setiap 10 menit @can('bookings.manage'), atau klik <b>Sinkronkan sekarang</b>@endcan.
    </div>
@endif

<div class="grid xl:grid-cols-[1fr_340px] gap-5 items-start">
    <div class="min-w-0 bg-white border border-line rounded-2xl p-4 sm:p-5">
        <div id="calendar" class="gie-calendar"></div>
        <div class="flex flex-wrap gap-4 mt-4 pt-4 border-t border-line text-[12px] text-muted">
            @foreach ($statusLabel as $k => $label)
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $colors[$k] }}"></span> Booking {{ $label }}</span>
            @endforeach
            <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $colors['google'] }}"></span> Event lain di Google Calendar</span>
        </div>
    </div>

    <div class="min-w-0 space-y-5">
        <div class="bg-white border border-line rounded-2xl">
            <div class="px-5 pt-5 pb-3 flex items-center justify-between">
                <h2 class="font-semibold">Event mendatang</h2>
                <span class="text-[11px] text-muted">60 hari ke depan</span>
            </div>
            <ul class="divide-y divide-line">
                @forelse ($upcoming as $e)
                    @php $isToday = $e['start']->isToday() || ($e['source'] === 'booking' && $e['start']->isPast()); @endphp
                    <li>
                        <a href="{{ $e['url'] }}" @if ($e['source'] === 'google') target="_blank" rel="noopener" @endif class="flex gap-3 px-5 py-3 hover:bg-soft/40">
                            <div class="w-11 shrink-0 text-center rounded-xl py-1.5 {{ $isToday ? 'bg-ink text-white' : 'bg-soft' }}">
                                <div class="text-[10px] uppercase font-semibold leading-none">{{ $e['start']->translatedFormat('M') }}</div>
                                <div class="text-lg font-bold leading-tight">{{ $e['start']->format('d') }}</div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[13px] font-semibold truncate">{{ $e['title'] }}</div>
                                <div class="text-[11px] text-muted truncate">
                                    {{ $e['code'] ? $e['code'].' · ' : '' }}{{ $e['when'] }}{{ $e['where'] ? ' · '.$e['where'] : '' }}
                                </div>
                                <div class="flex items-center gap-1.5 mt-1">
                                    @if ($e['source'] === 'google')
                                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-blue-50 text-blue-700">Google Calendar</span>
                                    @else
                                        <span class="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded {{ $e['status'] === 'confirmed' ? 'bg-ink text-white' : 'bg-soft' }}">{{ $e['status'] }}</span>
                                        @if ($configured)
                                            @if ($e['sync_error'])
                                                <span class="text-[10px] text-amber-700 flex items-center gap-0.5" title="{{ $e['sync_error'] }}"><i data-lucide="alert-triangle" class="w-3 h-3"></i> gagal sinkron</span>
                                            @elseif ($e['synced'])
                                                <span class="text-[10px] text-emerald-700 flex items-center gap-0.5"><i data-lucide="check" class="w-3 h-3"></i> di Google</span>
                                            @elseif ($e['status'] === 'pending')
                                                <span class="text-[10px] text-muted">menunggu DP</span>
                                            @endif
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-[13px] text-muted">Belum ada event dalam 60 hari ke depan.</li>
                @endforelse
            </ul>
        </div>

        <div class="bg-white border border-line rounded-2xl p-5 text-[12px] text-muted space-y-2">
            <h3 class="font-semibold text-ink text-[13px] flex items-center gap-1.5"><i data-lucide="info" class="w-4 h-4"></i> Cara kerja sinkron</h3>
            <p>Booking berstatus <b>Confirmed</b> dan <b>Completed</b> otomatis dikirim ke Google Calendar. Jika dibatalkan atau dihapus, event-nya ikut dihapus.</p>
            <p>Event yang dibuat tim langsung di Google Calendar (meeting, survei lokasi) ikut tampil di sini dengan warna biru.</p>
            @unless ($configured)
                @can('settings.manage')
                    <p class="text-amber-700">Belum terhubung: isi <code>GOOGLE_CALENDAR_ID</code> di <code>.env</code> server dan simpan file kunci service account
                        di <code>storage/app/private/google-calendar.json</code>.@if ($serviceEmail) Bagikan kalender ke <b>{{ $serviceEmail }}</b> dengan izin “Make changes to events”.@endif</p>
                @endcan
            @endunless
        </div>
    </div>
</div>

<style>
    .gie-calendar { --fc-border-color: #e8e9ec; --fc-today-bg-color: #f4f5f7; --fc-page-bg-color: #fff; --fc-neutral-bg-color: #fafafa;
        --fc-button-bg-color: #fff; --fc-button-border-color: #e8e9ec; --fc-button-text-color: #0b0b0c;
        --fc-button-hover-bg-color: #f4f5f7; --fc-button-hover-border-color: #e8e9ec;
        --fc-button-active-bg-color: #0b0b0c; --fc-button-active-border-color: #0b0b0c; font-size: 13px; }
    .gie-calendar .fc-toolbar-title { font-size: 17px; font-weight: 600; text-transform: capitalize; }
    .gie-calendar .fc-button { border-radius: 9999px !important; font-size: 12px; font-weight: 600; padding: .35rem .85rem; box-shadow: none !important; text-transform: capitalize; }
    .gie-calendar .fc-button-active { color: #fff !important; }
    .gie-calendar .fc-button-group { gap: 4px; }
    .gie-calendar .fc-col-header-cell-cushion { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #6b6f76; padding: 8px 4px; }
    .gie-calendar .fc-daygrid-day-number { font-size: 12px; color: #444; }
    .gie-calendar .fc-event { border-radius: 6px; padding: 1px 4px; font-size: 11px; cursor: pointer; }
    .gie-calendar .fc-toolbar.fc-header-toolbar { flex-wrap: wrap; gap: .5rem; }
    .gie-calendar .fc-toolbar.fc-footer-toolbar { margin-top: .75rem; }
    .gie-calendar .fc-list-event:hover td { background: #f4f5f7; }
    @media (max-width: 640px) { .gie-calendar .fc-toolbar-title { font-size: 15px; } }
</style>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales/id.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('calendar');
    const mobile = window.matchMedia('(max-width: 640px)').matches;
    const calendar = new FullCalendar.Calendar(el, {
        locale: 'id',
        initialView: mobile ? 'agenda' : 'dayGridMonth',
        // Agenda = daftar 3 bulan ke depan, supaya tidak kosong di bulan yang sepi
        views: { agenda: { type: 'list', duration: { months: 3 }, buttonText: 'Agenda 3 bln' } },
        height: 'auto',
        firstDay: 1,
        dayMaxEvents: 3,
        headerToolbar: mobile
            ? { left: 'prev,next', center: 'title', right: 'today' }
            : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,agenda' },
        footerToolbar: mobile ? { center: 'dayGridMonth,agenda' } : false,
        noEventsContent: 'Tidak ada event di rentang ini',
        // Ikon panah bawaan FullCalendar memakai font data: yang diblokir CSP (font-src), jadi pakai teks biasa
        buttonIcons: false,
        buttonText: { prev: '‹', next: '›', today: 'Hari ini', month: 'Bulan', week: 'Minggu' },
        events: { url: @json(route('admin.bookings.calendar.events')), failure: () => el.insertAdjacentHTML('beforebegin',
            '<div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 text-sm mb-3">Gagal memuat data kalender. Muat ulang halaman.</div>') },
        eventDidMount(info) {
            const p = info.event.extendedProps;
            info.el.title = [info.event.title, p.venue, p.customer ? 'Customer: ' + p.customer : null,
                p.source === 'google' ? 'Dari Google Calendar' : null].filter(Boolean).join('\n');
        },
        eventClick(info) {
            // Event Google dibuka di tab baru; booking dibuka di halaman detail admin
            if (info.event.extendedProps.source === 'google' && info.event.url) {
                info.jsEvent.preventDefault();
                window.open(info.event.url, '_blank', 'noopener');
            }
        },
    });
    calendar.render();
});
</script>
@endsection
