@extends('layouts.app')
@section('title', 'Booking')

@section('content')
<form method="GET" class="flex flex-wrap items-center gap-3 mb-4">
    <div class="flex items-center gap-2 bg-white border border-line rounded-full px-4 h-10 w-full sm:w-72">
        <i data-lucide="search" class="w-4 h-4 text-muted"></i>
        <input name="q" value="{{ $search }}" placeholder="Kode, event, customer..." class="text-sm outline-none w-full">
    </div>
    <div class="flex bg-white border border-line rounded-full p-1 text-[12px] font-semibold">
        <a href="{{ route('admin.bookings.index') }}" class="px-3 py-1.5 rounded-full {{ ! $status ? 'bg-ink text-white' : '' }}">Semua</a>
        @foreach (\App\Models\Booking::STATUSES as $k => $v)
            <a href="{{ route('admin.bookings.index', ['status' => $k]) }}" class="px-3 py-1.5 rounded-full {{ $status === $k ? 'bg-ink text-white' : '' }}">{{ ucfirst($k) }}</a>
        @endforeach
    </div>
</form>

<div class="bg-white border border-line rounded-2xl overflow-x-auto">
    <table class="w-full text-[13px]">
        <thead class="bg-soft/60 text-left text-[11px] uppercase tracking-wider text-muted">
            <tr><th class="px-4 py-3">Kode</th><th class="px-4">Event</th><th class="px-4">Customer</th><th class="px-4">Tanggal</th><th class="px-4">Total</th><th class="px-4">Bayar</th><th class="px-4">Status</th><th class="px-4">Tahap</th></tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($bookings as $b)
                <tr class="hover:bg-soft/40 cursor-pointer" onclick="location='{{ route('admin.bookings.show', $b) }}'">
                    <td class="px-4 py-3 font-semibold">{{ $b->code }}</td>
                    <td class="px-4">{{ $b->event_name }}<div class="text-[11px] text-muted">{{ $b->venue_name }}</div></td>
                    <td class="px-4">{{ $b->customer_name }}</td>
                    <td class="px-4 whitespace-nowrap">{{ $b->date_range }}</td>
                    <td class="px-4 whitespace-nowrap">{{ rupiah($b->total) }}</td>
                    <td class="px-4">{{ $b->paid_percent }}%</td>
                    <td class="px-4"><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $b->status === 'confirmed' ? 'bg-ink text-white' : ($b->status === 'cancelled' ? 'bg-red-100 text-red-700' : 'bg-soft') }}">{{ $b->status }}</span></td>
                    <td class="px-4 text-[12px]">{{ \App\Models\Booking::STAGES[$b->stage]['label'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="px-4 py-10 text-center text-muted">Belum ada booking.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $bookings->links() }}</div>
@endsection
