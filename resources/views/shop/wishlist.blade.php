@extends('layouts.app')
@section('title', 'Wishlist')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-10">
    <div class="eyebrow flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-ink"></span> Saved</div>
    <h1 class="text-3xl font-semibold tracking-tight mt-2">Wishlist</h1>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-8">
        @forelse ($items as $item)
            @if ($item instanceof \App\Models\Product)
                @include('partials.ui', ['ui' => 'product', 'product' => $item])
            @else
                @include('partials.ui', ['ui' => 'package', 'package' => $item, 'variant' => 'compact'])
            @endif
        @empty
            <div class="col-span-full text-center text-muted py-16">Belum ada item yang disimpan. Klik ikon ♡ pada produk atau paket.</div>
        @endforelse
    </div>
</div>
@endsection
