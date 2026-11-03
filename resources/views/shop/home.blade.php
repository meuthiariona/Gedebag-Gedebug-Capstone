@extends('layouts.app')
@section('title', 'Home')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6" x-data="{ tab: 'all' }">

    {{-- HERO --}}
    @if ($slides->isNotEmpty())
    <section class="mt-6" x-data="{ i: 0, n: {{ $slides->count() }} }" x-init="n > 1 && setInterval(() => i = (i + 1) % n, 7000)">
        <div class="relative rounded-2xl overflow-hidden bg-ink text-white min-h-[420px] md:min-h-[460px]">
            @foreach ($slides as $k => $s)
                <div x-show="i === {{ $k }}" x-transition.opacity.duration.700ms class="absolute inset-0" @if($k) x-cloak @endif>
                    <img src="{{ $s->image_url }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-55">
                    <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/50 to-transparent"></div>
                    <div class="relative h-full flex flex-col justify-center px-6 sm:px-14 py-16 max-w-2xl">
                        @if ($s->eyebrow)<div class="eyebrow text-white/80 mb-4">{{ $s->eyebrow }}</div>@endif
                        <h1 class="text-4xl sm:text-5xl font-extrabold tracking-tight leading-[1.05] uppercase">{{ $s->title_bold }}
                            @if ($s->title_light)<br><span class="font-light">{{ $s->title_light }}</span>@endif
                        </h1>
                        @if ($s->subtitle)<p class="text-white/75 text-sm mt-5 max-w-md leading-relaxed">{{ $s->subtitle }}</p>@endif
                        <div class="flex flex-wrap gap-3 mt-7">
                            @if ($s->button_text)
                                <a href="{{ $s->button_url ?: route('products') }}" class="h-10 px-5 rounded-full bg-white text-ink text-[12px] font-bold flex items-center gap-2">{{ $s->button_text }} <i data-lucide="arrow-right" class="w-4 h-4"></i></a>
                            @endif
                            @if ($s->secondary_text)
                                <a href="{{ $s->secondary_url ?: '#' }}" target="_blank" class="h-10 px-5 rounded-full border border-white/30 text-[12px] font-bold flex items-center gap-2 hover:bg-white/10"><i data-lucide="circle-play" class="w-4 h-4"></i> {{ $s->secondary_text }}</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
            @if ($slides->count() > 1)
                <div class="absolute right-5 bottom-5 flex gap-2">
                    <button @click="i = (i - 1 + n) % n" class="grid place-items-center w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
                    <button @click="i = (i + 1) % n" class="grid place-items-center w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
                </div>
            @endif
        </div>
    </section>
    @endif

    {{-- TAB KATEGORI --}}
    <div class="flex gap-2 overflow-x-auto scrollbar-none mt-8 pb-1">
        <button @click="tab = 'all'" :class="tab === 'all' ? 'bg-ink text-white border-ink' : 'bg-white hover:bg-soft'" class="shrink-0 h-9 px-4 rounded-full border border-line text-[12px] font-semibold flex items-center gap-2">
            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> All
        </button>
        @foreach ($categories as $c)
            <button @click="tab = '{{ $c->slug }}'" :class="tab === '{{ $c->slug }}' ? 'bg-ink text-white border-ink' : 'bg-white hover:bg-soft'" class="shrink-0 h-9 px-4 rounded-full border border-line text-[12px] font-semibold flex items-center gap-2">
                <i data-lucide="{{ $c->icon ?: 'box' }}" class="w-3.5 h-3.5"></i> {{ $c->name }}
            </button>
        @endforeach
        <button @click="tab = 'packages'" :class="tab === 'packages' ? 'bg-ink text-white border-ink' : 'bg-white hover:bg-soft'" class="shrink-0 h-9 px-4 rounded-full border border-line text-[12px] font-semibold flex items-center gap-2">
            <i data-lucide="package" class="w-3.5 h-3.5"></i> Packages
        </button>
    </div>

    {{-- SECTION PER KATEGORI --}}
    @foreach ($categories as $c)
        <section class="mt-10" x-show="tab === 'all' || tab === '{{ $c->slug }}'">
            <div class="flex items-end justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">{{ $c->name }}</h2>
                    <p class="text-[12px] text-muted mt-0.5">{{ $c->description }}</p>
                </div>
                <a href="{{ route('products', ['category' => $c->slug]) }}" class="text-[12px] font-semibold flex items-center gap-1 hover:underline">View All <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($c->products->take(4) as $product)
                    @include('partials.ui', ['ui' => 'product'])
                @endforeach
            </div>
        </section>
    @endforeach

    {{-- PAKET --}}
    @if ($packages->isNotEmpty())
        <section class="mt-10" x-show="tab === 'all' || tab === 'packages'">
            <div class="flex items-end justify-between mb-4">
                <div>
                    <h2 class="text-2xl font-semibold tracking-tight">Packages</h2>
                    <p class="text-[12px] text-muted mt-0.5">Integrated, turn-key setups inclusive of crew, transport, and certified engineers.</p>
                </div>
                <a href="{{ route('packages') }}" class="text-[12px] font-semibold flex items-center gap-1 hover:underline">View All <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></a>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach ($packages as $package)
                    @include('partials.ui', ['ui' => 'package', 'variant' => 'compact'])
                @endforeach
            </div>
        </section>
    @endif

    {{-- STRIP SERTIFIKASI --}}
    <section class="mt-14 bg-soft rounded-2xl px-6 py-5 flex flex-col md:flex-row md:items-center gap-4">
        <div class="flex items-center gap-4">
            <span class="grid place-items-center w-11 h-11 rounded-full bg-ink text-white shrink-0"><i data-lucide="badge-check" class="w-5 h-5"></i></span>
            <div>
                <div class="text-[14px] font-semibold">{{ setting('certified_title', 'Certified Sound & Lighting Technicians Included') }}</div>
                <div class="text-[11.5px] text-muted">{{ setting('certified_text', 'Every rental tier includes full transport logistics, rig check, and live audio engineer standby.') }}</div>
            </div>
        </div>
        <div class="md:ml-auto flex flex-wrap gap-2">
            @include('partials.ui', ['ui' => 'tech-specs', 'class' => 'h-10 px-5 rounded-full border border-line bg-white text-[12px] font-semibold flex items-center gap-2'])
            <a href="https://wa.me/{{ setting('whatsapp', '6281234567890') }}?text={{ urlencode('Halo, saya ingin konsultasi dengan Sound Director NEXA.') }}" target="_blank" class="h-10 px-5 rounded-full bg-ink text-white text-[12px] font-bold flex items-center gap-2">Speak with Sound Director</a>
        </div>
    </section>
</div>
@endsection
