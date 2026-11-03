@extends('layouts.app')
@section('title', $package->name)

@php
    [$rs, $re, $days] = rental_dates();
    $images = array_values(array_unique(array_merge([$package->image_url], $package->gallery_urls)));
    $groups = collect(\App\Models\PackageItem::TYPES)->map(fn ($label, $type) => ['label' => $label, 'items' => $package->items->where('type', $type)])->filter(fn ($g) => $g['items']->isNotEmpty());
    $saved = in_array('package-'.$package->id, $wishKeys ?? []);
@endphp

@section('subbar')
<div class="bg-white border-b border-line">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-3 text-[11px]">
        <a href="{{ route('home') }}" class="hover:underline">Home</a><i data-lucide="chevron-right" class="w-3 h-3 text-muted"></i>
        <a href="{{ route('packages') }}" class="hover:underline">Packages</a><i data-lucide="chevron-right" class="w-3 h-3 text-muted"></i>
        <span class="font-semibold">{{ $package->name }}</span>
        <div class="ml-auto flex items-center gap-2 bg-soft rounded-full px-3 py-1">
            <i data-lucide="calendar" class="w-3.5 h-3.5"></i> Selected: {{ $rs->format('j M Y') }} – {{ $re->format('j M Y') }} ({{ $days }} Days)
            <span class="w-1.5 h-1.5 rounded-full {{ $package->available ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
            <span class="font-semibold">{{ $package->available }} Units Avail.</span>
        </div>
    </div>
</div>
@endsection

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-8"
     x-data="{ base: {{ $package->price }}, addons: {}, get extra() { return Object.values(this.addons).reduce((a, b) => a + b, 0) }, get total() { return this.base + this.extra }, img: 0 }">
    <div class="grid lg:grid-cols-[1fr_400px] gap-8 items-start">
        {{-- KIRI --}}
        <div>
            <div class="relative rounded-2xl overflow-hidden bg-ink aspect-[16/10]">
                @foreach ($images as $k => $src)
                    <img x-show="img === {{ $k }}" src="{{ $src }}" alt="{{ $package->name }}" class="absolute inset-0 w-full h-full object-cover" @if($k) x-cloak @endif>
                @endforeach
                <div class="absolute top-3 left-3 flex flex-wrap gap-1.5">
                    @foreach ($package->tags ?? [] as $i => $tag)
                        <span class="text-[10px] font-bold tracking-[.1em] uppercase px-2.5 py-1 rounded {{ $i === 0 ? 'bg-ink text-white' : 'bg-white text-ink' }}">{{ $tag }}</span>
                    @endforeach
                </div>
                <form action="{{ route('wishlist.toggle', ['package', $package->id]) }}" method="POST" class="absolute top-3 right-3">@csrf
                    <button class="heart {{ $saved ? 'is-saved' : '' }} grid place-items-center w-9 h-9 rounded-full bg-white/90"><i data-lucide="heart" class="w-4 h-4"></i></button>
                </form>
            </div>
            @if (count($images) > 1)
                <div class="grid grid-cols-4 gap-2 mt-2">
                    @foreach (array_slice($images, 0, 4) as $k => $src)
                        <button @click="img = {{ $k }}" :class="img === {{ $k }} ? 'ring-2 ring-ink' : 'opacity-80 hover:opacity-100'" class="aspect-[4/3] rounded-lg overflow-hidden bg-soft">
                            <img src="{{ $src }}" alt="" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="eyebrow text-muted mt-7">
                {{ $package->series_label }}
                @if ($package->capacity_max) • Capacity: {{ $package->capacity_min }} – {{ $package->capacity_max }} pax @endif
            </div>
            <h1 class="text-3xl font-semibold tracking-tight mt-1.5">{{ $package->name }}</h1>
            <p class="text-[13px] text-muted mt-2 leading-relaxed">{{ $package->description }}</p>

            {{-- Isi paket --}}
            @if ($groups->isNotEmpty())
            <div class="mt-7 border border-line rounded-2xl p-3" x-data="{ t: '{{ $groups->keys()->first() }}' }">
                <div class="flex flex-wrap gap-2 mb-3">
                    @foreach ($groups as $type => $g)
                        <button @click="t = '{{ $type }}'" :class="t === '{{ $type }}' ? 'bg-ink text-white' : 'bg-soft'" class="h-8 px-3.5 rounded-full text-[11.5px] font-semibold flex items-center gap-1.5">
                            <i data-lucide="{{ ['hardware' => 'speaker', 'talent' => 'users', 'service' => 'circle-check'][$type] }}" class="w-3.5 h-3.5"></i>
                            {{ $g['label'] }} ({{ $g['items']->count() }})
                        </button>
                    @endforeach
                </div>
                @foreach ($groups as $type => $g)
                    <div x-show="t === '{{ $type }}'" class="space-y-2" @if(! $loop->first) x-cloak @endif>
                        @foreach ($g['items'] as $item)
                            <div class="flex items-center gap-4 bg-soft rounded-xl px-4 py-3">
                                <span class="grid place-items-center w-8 h-8 rounded-lg bg-white text-[11px] font-bold shrink-0">{{ $item->qty }}×</span>
                                <div class="min-w-0">
                                    <div class="text-[13.5px] font-semibold">{{ $item->name }}</div>
                                    <div class="text-[11px] text-muted">{{ $item->subtitle }}</div>
                                </div>
                                @if ($item->tag)<span class="ml-auto eyebrow text-muted text-right">{{ $item->tag }}</span>@endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            @endif

            {{-- Add-on --}}
            @if ($package->addons->isNotEmpty())
            <div class="mt-6 border border-line rounded-2xl p-5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="eyebrow text-muted">Dynamic Rig Configuration</div>
                        <h2 class="text-lg font-semibold mt-1">Customize Your Production</h2>
                        <p class="text-[11.5px] text-muted">Butuh coverage tambahan, video switching, atau hiburan live? Centang add-on di bawah:</p>
                    </div>
                    <span class="text-[10.5px] text-muted">Instant Price Update</span>
                </div>
                <div class="space-y-2 mt-4">
                    @foreach ($package->addons as $addon)
                        <label class="flex items-center gap-3 bg-soft rounded-xl px-4 py-3 cursor-pointer hover:bg-line/60">
                            <input type="checkbox" form="add-package" name="addons[]" value="{{ $addon->id }}" class="w-4 h-4 accent-black"
                                   @change="$event.target.checked ? addons[{{ $addon->id }}] = {{ $addon->price }} : delete addons[{{ $addon->id }}]">
                            <div class="min-w-0">
                                <div class="text-[13.5px] font-semibold">{{ $addon->name }}</div>
                                <div class="text-[11px] text-muted">{{ $addon->description }}</div>
                            </div>
                            <div class="ml-auto text-right shrink-0">
                                <div class="text-[13px] font-bold">+ {{ rupiah($addon->price) }}</div>
                                <div class="text-[10px] text-muted">/ {{ $addon->unit }}</div>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- KANAN: ringkasan harga --}}
        <aside class="lg:sticky lg:top-24 space-y-4">
            <div class="border border-line rounded-2xl p-5 shadow-card">
                <div class="flex items-start justify-between">
                    <div class="eyebrow text-muted">Package Total</div>
                    @if ($package->tier)<span class="bg-ink text-white text-[10px] font-bold px-2 py-0.5 rounded">{{ $package->tier }}</span>@endif
                </div>
                <div class="mt-1"><span class="text-3xl font-bold tracking-tight" x-text="'Rp ' + total.toLocaleString('id-ID')">{{ rupiah($package->price) }}</span> <span class="text-[11px] text-muted">/ {{ $days }}-day rental</span></div>

                <div class="bg-soft rounded-xl p-3 mt-4 text-[11.5px] space-y-1.5">
                    <div class="flex justify-between"><span class="flex items-center gap-1.5 text-muted"><i data-lucide="calendar" class="w-3.5 h-3.5"></i> Booking Window:</span><span class="font-semibold">{{ $rs->format('j M') }} – {{ $re->format('j M Y') }}</span></div>
                    <div class="flex justify-between"><span class="flex items-center gap-1.5 text-muted"><i data-lucide="map-pin" class="w-3.5 h-3.5"></i> Logistics:</span><span class="font-semibold">{{ setting('support_region', 'Regional Dispatch') }}</span></div>
                    <div class="flex justify-between"><span class="flex items-center gap-1.5 text-muted"><i data-lucide="box" class="w-3.5 h-3.5"></i> Availability:</span>
                        <span class="font-semibold {{ $package->available ? 'text-emerald-600' : 'text-red-600' }}">{{ $package->available ? '✓ '.$package->available.' In Stock' : 'Fully booked' }}</span></div>
                </div>

                <div class="text-[11.5px] space-y-1.5 mt-4">
                    <div class="flex justify-between"><span class="text-muted">Base Turnkey Rig & Host</span><span>{{ rupiah($package->price) }}</span></div>
                    <div class="flex justify-between"><span class="text-muted">Custom Add-ons Selected</span><span x-text="'Rp ' + extra.toLocaleString('id-ID')">Rp 0</span></div>
                    <div class="flex justify-between"><span class="text-muted">{{ $package->engineers }}× Audio & Light Engineers</span><span class="text-emerald-600 font-bold text-[10.5px]">INCLUDED</span></div>
                    <div class="flex justify-between"><span class="text-muted">Delivery & Pre-rigging Setup</span><span class="text-emerald-600 font-bold text-[10.5px]">INCLUDED</span></div>
                </div>
                <div class="flex justify-between items-end border-t border-line mt-4 pt-4">
                    <div><div class="text-[14px] font-semibold">Estimated Investment</div><div class="text-[10.5px] text-muted">Includes taxes & production guarantee</div></div>
                    <div class="text-lg font-bold" x-text="'Rp ' + total.toLocaleString('id-ID')">{{ rupiah($package->price) }}</div>
                </div>

                <form id="add-package" action="{{ route('cart.package', $package) }}" method="POST" class="mt-5 space-y-2">@csrf
                    <button name="checkout" value="1" @disabled(! $package->available) class="w-full h-11 rounded-full bg-ink text-white text-[13px] font-bold flex items-center justify-center gap-2 disabled:opacity-40">
                        <i data-lucide="shopping-bag" class="w-4 h-4"></i> Add Package to Cart <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </form>
                <form action="{{ route('wishlist.toggle', ['package', $package->id]) }}" method="POST">@csrf
                    <button class="w-full h-10 rounded-full bg-soft text-[12px] font-semibold flex items-center justify-center gap-2 mt-2">
                        <i data-lucide="bookmark" class="w-4 h-4"></i> {{ $saved ? 'Remove from Wishlist' : 'Save Configuration to Wishlist' }}
                    </button>
                </form>
                <div class="grid grid-cols-3 text-center text-[9.5px] text-muted mt-5 gap-2">
                    <div><i data-lucide="lock" class="w-4 h-4 mx-auto mb-1"></i>256-Bit Secure Escrow</div>
                    <div><i data-lucide="headset" class="w-4 h-4 mx-auto mb-1"></i>24/7 Dispatch Engineer</div>
                    <div><i data-lucide="shield-check" class="w-4 h-4 mx-auto mb-1"></i>Hardware Rider Guarantee</div>
                </div>
            </div>

            <div class="bg-soft rounded-2xl p-4 flex items-center gap-3">
                <i data-lucide="sliders-horizontal" class="w-5 h-5"></i>
                <div><div class="text-[13px] font-semibold">Bespoke Rider Requirements?</div><div class="text-[11px] text-muted">Speak directly with our technical lead engineer.</div></div>
                <a href="https://wa.me/{{ setting('whatsapp', '6281234567890') }}?text={{ urlencode('Halo, saya ingin diskusi rider untuk '.$package->name) }}" target="_blank" class="ml-auto text-[11.5px] font-semibold flex items-center gap-1 shrink-0">Chat Lead <i data-lucide="external-link" class="w-3.5 h-3.5"></i></a>
            </div>
        </aside>
    </div>
</div>

{{-- PAKET TERKAIT --}}
@if ($related->isNotEmpty())
<section class="bg-soft/70 mt-16 py-12">
    <div class="max-w-[1180px] mx-auto px-4 sm:px-6">
        <div class="flex items-end justify-between mb-5">
            <div><div class="eyebrow text-muted">Complementary Solutions</div><h2 class="text-2xl font-semibold tracking-tight mt-1">Related Production Packages</h2></div>
            <a href="{{ route('packages') }}" class="text-[12px] font-semibold flex items-center gap-1 hover:underline">View All Packages <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($related as $r)
                <div class="bg-white rounded-2xl overflow-hidden border border-line shadow-card flex flex-col">
                    <a href="{{ route('packages.show', $r->slug) }}" class="relative block aspect-[16/10] bg-ink">
                        <img src="{{ $r->image_url }}" alt="{{ $r->name }}" class="w-full h-full object-cover">
                        @if ($r->badge)<span class="absolute left-3 bottom-3 bg-white text-ink text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded">{{ $r->badge }}</span>@endif
                    </a>
                    <div class="p-4 flex-1 flex flex-col">
                        <a href="{{ route('packages.show', $r->slug) }}" class="text-[14px] font-semibold hover:underline">{{ $r->name }}</a>
                        <p class="text-[11px] text-muted mt-1 line-clamp-2">{{ $r->summary }}</p>
                        <div class="flex gap-3 text-[10.5px] font-semibold mt-2">
                            @if ($r->capacity_max)<span class="flex items-center gap-1"><i data-lucide="users" class="w-3 h-3"></i> Up to {{ number_format($r->capacity_max, 0, ',', '.') }} Pax</span>@endif
                            @if ($r->power_watts)<span class="flex items-center gap-1"><i data-lucide="zap" class="w-3 h-3"></i> {{ number_format($r->power_watts, 0, ',', '.') }} Watts</span>@endif
                        </div>
                        <div class="flex items-end justify-between mt-auto pt-4">
                            <div><div class="text-[15px] font-bold">{{ rupiah($r->price) }}</div><div class="text-[10.5px] text-muted">/ {{ $r->unit }}</div></div>
                            <form action="{{ route('cart.package', $r) }}" method="POST">@csrf
                                <button class="grid place-items-center w-9 h-9 rounded-full bg-ink text-white"><i data-lucide="plus" class="w-4 h-4"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
@endsection
