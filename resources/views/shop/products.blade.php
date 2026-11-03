@extends('layouts.app')
@section('title', 'Products')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-10">
    <div class="eyebrow flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-ink"></span> Equipment & Talent Catalog</div>
    <h1 class="text-3xl font-semibold tracking-tight mt-2">{{ $search ? 'Hasil pencarian "'.$search.'"' : 'Products' }}</h1>
    <p class="text-[13px] text-muted mt-1">Harga per unit, stok dihitung real-time untuk tanggal {{ rental_range_label() }}.</p>

    <div class="flex gap-2 overflow-x-auto scrollbar-none mt-6 pb-1">
        <a href="{{ route('products', array_filter(['q' => $search])) }}" class="shrink-0 h-9 px-4 rounded-full border text-[12px] font-semibold flex items-center gap-2 {{ ! $active ? 'bg-ink text-white border-ink' : 'border-line hover:bg-soft' }}">
            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> All
        </a>
        @foreach ($categories as $c)
            <a href="{{ route('products', array_filter(['category' => $c->slug, 'q' => $search])) }}" class="shrink-0 h-9 px-4 rounded-full border text-[12px] font-semibold flex items-center gap-2 {{ $active === $c->slug ? 'bg-ink text-white border-ink' : 'border-line hover:bg-soft' }}">
                <i data-lucide="{{ $c->icon ?: 'box' }}" class="w-3.5 h-3.5"></i> {{ $c->name }}
            </a>
        @endforeach
        <a href="{{ route('packages') }}" class="shrink-0 h-9 px-4 rounded-full border border-line text-[12px] font-semibold flex items-center gap-2 hover:bg-soft"><i data-lucide="package" class="w-3.5 h-3.5"></i> Packages</a>
    </div>

    @forelse ($grouped as $g)
        <section class="mt-10">
            <h2 class="text-2xl font-semibold tracking-tight">{{ $g['category']->name }}</h2>
            <p class="text-[12px] text-muted mt-0.5 mb-4">{{ $g['category']->description }}</p>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($g['products'] as $product)
                    @include('partials.ui', ['ui' => 'product'])
                @endforeach
            </div>
        </section>
    @empty
        @if ($packages->isEmpty())
            <div class="mt-16 text-center text-muted">
                <i data-lucide="search-x" class="w-10 h-10 mx-auto mb-3"></i>
                Tidak ada produk yang cocok.
            </div>
        @endif
    @endforelse

    @if ($packages->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-2xl font-semibold tracking-tight mb-4">Packages</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($packages as $package)
                    @include('partials.ui', ['ui' => 'package', 'variant' => 'compact'])
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
