@extends('layouts.app')
@section('title', 'Kalender '.$product->name)

@section('content')
<section class="max-w-3xl mx-auto px-4 sm:px-6 pt-8">
    <a href="{{ route('products') }}" class="text-sm text-muted">← Katalog</a>
    <h1 class="text-2xl font-semibold mt-3">{{ $product->name }}</h1>
    <div class="flex justify-between items-center mt-5 mb-3">
        <div>
            @if ($month->gt($firstMonth))
                <a href="{{ route('products.availability', [$product, 'month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="text-sm underline">← Sebelumnya</a>
            @endif
        </div>
        <h2 class="font-semibold">{{ $month->translatedFormat('F Y') }}</h2>
        <div>
            @if ($month->lt($lastMonth))
                <a href="{{ route('products.availability', [$product, 'month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="text-sm underline">Berikutnya →</a>
            @endif
        </div>
    </div>
    <div class="grid grid-cols-7 gap-1 sm:gap-2 text-center">
        @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $weekday)
            <div class="py-2 text-xs font-semibold">{{ $weekday }}</div>
        @endforeach
        @for ($blank = 1; $blank < $month->dayOfWeekIso; $blank++)
            <div></div>
        @endfor
        @foreach ($days as $date => $qty)
            @php($past = $date <= today()->toDateString())
            <div class="rounded-lg border border-line py-2 {{ $past ? 'bg-soft text-muted' : ($qty ? 'bg-emerald-50 text-emerald-900' : 'bg-red-50 text-red-900') }}">
                <div class="font-semibold text-sm">{{ (int) substr($date, -2) }}</div>
                <div class="text-[10px] sm:text-xs">{{ $past ? '—' : ($qty ? $qty.' unit' : 'Penuh') }}</div>
            </div>
        @endforeach
    </div>
    <p class="text-sm text-muted mt-3">Stok bisa berubah sampai booking dibuat.</p>
</section>
@endsection
