@extends('layouts.app')
@section('title', 'Cart')

@section('content')
@php([$rs, $re, $days] = rental_dates())
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-10">
    <div class="eyebrow flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-ink"></span> Rental Cart</div>
    <h1 class="text-3xl font-semibold tracking-tight mt-2">Keranjang Sewa</h1>
    <p class="text-[13px] text-muted mt-1">Periode sewa: <b class="text-ink">{{ $rs->format('j M Y') }} – {{ $re->format('j M Y') }}</b> ({{ $days }} hari). Ubah tanggal lewat bar di atas.</p>

    @if (! $lines)
        <div class="text-center py-20 border border-dashed border-line rounded-2xl mt-8">
            <i data-lucide="shopping-bag" class="w-10 h-10 mx-auto text-muted"></i>
            <p class="text-muted mt-3">Keranjang masih kosong.</p>
            <div class="flex justify-center gap-3 mt-5">
                <a href="{{ route('products') }}" class="h-10 px-5 rounded-full border border-line text-[12px] font-semibold flex items-center">Browse Products</a>
                <a href="{{ route('packages') }}" class="h-10 px-5 rounded-full bg-ink text-white text-[12px] font-bold flex items-center">Lihat Paket</a>
            </div>
        </div>
    @else
        <div class="grid lg:grid-cols-[1fr_360px] gap-8 mt-8 items-start">
            <div class="space-y-3">
                @foreach ($lines as $line)
                    <div class="flex gap-4 border border-line rounded-2xl p-3 items-center">
                        <img src="{{ $line['image'] }}" alt="" class="w-20 h-20 rounded-xl object-cover bg-soft shrink-0">
                        <div class="min-w-0 flex-1">
                            <span class="eyebrow text-muted">{{ $line['category'] }}</span>
                            <div class="font-semibold text-[14px]">{{ $line['name'] }}</div>
                            <div class="text-[11px] text-muted line-clamp-1">{{ $line['subtitle'] }}</div>
                            @foreach ($line['addons'] as $addon)
                                <div class="text-[11px] mt-0.5">+ {{ $addon->name }} <span class="text-muted">({{ rupiah($addon->price) }})</span></div>
                            @endforeach
                            <div class="text-[11px] mt-1 {{ $line['qty'] > $line['available'] ? 'text-red-600 font-semibold' : 'text-emerald-600' }}">
                                {{ $line['qty'] > $line['available'] ? 'Stok tidak cukup — tersedia '.$line['available'] : $line['available'].' unit tersedia untuk tanggal ini' }}
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="font-bold text-[14px]">{{ rupiah($line['subtotal']) }}</div>
                            <div class="text-[10.5px] text-muted">{{ rupiah($line['rate']) }} /{{ $line['unit'] }}{{ $line['days'] > 1 ? ' × '.$line['days'].' hari' : '' }}</div>
                            <div class="flex items-center justify-end gap-2 mt-2">
                                <form action="{{ route('cart.update', $line['key']) }}" method="POST" class="flex items-center border border-line rounded-full">@csrf @method('PATCH')
                                    <button name="qty" value="{{ $line['qty'] - 1 }}" class="w-7 h-7 grid place-items-center"><i data-lucide="minus" class="w-3 h-3"></i></button>
                                    <span class="text-[12px] font-semibold w-5 text-center">{{ $line['qty'] }}</span>
                                    <button name="qty" value="{{ $line['qty'] + 1 }}" class="w-7 h-7 grid place-items-center"><i data-lucide="plus" class="w-3 h-3"></i></button>
                                </form>
                                <form action="{{ route('cart.remove', $line['key']) }}" method="POST">@csrf @method('DELETE')
                                    <button class="w-7 h-7 grid place-items-center rounded-full hover:bg-red-50 text-red-600"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="border border-line rounded-2xl p-5 shadow-card lg:sticky lg:top-24">
                <div class="eyebrow text-muted">Order Summary</div>
                <div class="text-[12.5px] space-y-2 mt-4">
                    <div class="flex justify-between"><span class="text-muted">Subtotal peralatan & talent</span><span>{{ rupiah($totals['subtotal']) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Logistics, rigging & delivery</span><span>{{ rupiah($totals['logistics']) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Sound & light engineers</span><span class="text-emerald-600 font-bold text-[10.5px]">INCLUDED</span></div>
                </div>
                <div class="flex justify-between items-end border-t border-line mt-4 pt-4">
                    <span class="font-semibold">Grand Total</span><span class="text-xl font-bold">{{ rupiah($totals['total']) }}</span>
                </div>
                <div class="text-[11px] text-muted mt-1">DP {{ setting('dp_percent', 50) }}%: <b class="text-ink">{{ rupiah(round($totals['total'] * setting('dp_percent', 50) / 100)) }}</b></div>
                <a href="{{ route('checkout') }}" class="mt-5 w-full h-11 rounded-full bg-ink text-white text-[13px] font-bold flex items-center justify-center gap-2">Lanjut Checkout <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
                <a href="{{ route('products') }}" class="mt-2 w-full h-10 rounded-full bg-soft text-[12px] font-semibold flex items-center justify-center">Tambah item lain</a>
            </aside>
        </div>
    @endif
</div>
@endsection
