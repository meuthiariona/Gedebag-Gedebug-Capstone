@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
    @foreach ($stats as $label => $value)
        <div class="bg-white border border-line rounded-2xl p-5">
            <div class="text-[11px] uppercase tracking-wider text-muted font-semibold">{{ $label }}</div>
            <div class="text-2xl font-bold mt-1">{{ $value }}</div>
        </div>
    @endforeach
</div>

<div class="grid xl:grid-cols-2 gap-5 mt-6">
    <div class="bg-white border border-line rounded-2xl p-5">
        <h2 class="font-semibold mb-3">Pembayaran menunggu verifikasi</h2>
        <div class="divide-y divide-line">
            @forelse ($pendingPayments as $p)
                <div class="py-3 flex flex-wrap items-center gap-3 text-[13px]">
                    <a href="{{ media_url($p->proof) }}" target="_blank"><img src="{{ media_url($p->proof) }}" class="w-12 h-12 rounded-lg object-cover bg-soft"></a>
                    <div>
                        <a href="{{ route('admin.bookings.show', $p->booking) }}" class="font-semibold hover:underline">#{{ $p->booking->code }}</a> · {{ $p->booking->customer_name }}
                        <div class="text-[11px] text-muted">{{ $p->method }} · ref {{ $p->reference ?: '-' }} · {{ $p->created_at->diffForHumans() }}</div>
                    </div>
                    <b class="ml-auto">{{ rupiah($p->amount) }}</b>
                    <form action="{{ route('admin.payments.verify', $p) }}" method="POST" class="flex gap-1">@csrf
                        <button name="action" value="verify" class="h-8 px-3 rounded-full bg-emerald-600 text-white text-[12px] font-semibold">Verifikasi</button>
                        <button name="action" value="reject" class="h-8 px-3 rounded-full bg-red-50 text-red-600 text-[12px] font-semibold">Tolak</button>
                    </form>
                </div>
            @empty
                <div class="text-muted text-sm py-4">Tidak ada pembayaran pending.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white border border-line rounded-2xl p-5">
        <h2 class="font-semibold mb-3">Event mendatang</h2>
        <div class="divide-y divide-line">
            @forelse ($upcoming as $b)
                <a href="{{ route('admin.bookings.show', $b) }}" class="py-3 flex items-center gap-3 text-[13px] hover:bg-soft/50 -mx-2 px-2 rounded-lg">
                    <div class="text-center w-12 shrink-0"><div class="text-[10px] uppercase text-muted">{{ $b->start_date->format('M') }}</div><div class="text-lg font-bold leading-none">{{ $b->start_date->format('d') }}</div></div>
                    <div class="min-w-0"><div class="font-semibold truncate">{{ $b->event_name }}</div><div class="text-[11px] text-muted">#{{ $b->code }} · {{ $b->customer_name }} · {{ $b->venue_name }}</div></div>
                    <span class="ml-auto text-[10px] font-bold uppercase px-2 py-0.5 rounded {{ $b->status === 'confirmed' ? 'bg-ink text-white' : 'bg-soft' }}">{{ $b->status }}</span>
                </a>
            @empty
                <div class="text-muted text-sm py-4">Belum ada event.</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
