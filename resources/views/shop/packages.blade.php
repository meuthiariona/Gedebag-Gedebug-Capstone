@extends('layouts.app')
@section('title', 'Packages')

@section('content')
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-10">
    <div class="flex flex-col md:flex-row md:items-end gap-4">
        <div>
            <div class="eyebrow flex items-center gap-2"><span class="w-1.5 h-1.5 rounded-full bg-ink"></span> Full Turnkey Production</div>
            <h1 class="text-3xl font-semibold tracking-tight mt-2">{{ setting('packages_title', 'Event Packages') }}</h1>
            <p class="text-[13px] text-muted mt-1">{{ setting('packages_subtitle', 'Complete solutions for your event with sound, lighting, engineers, and talent bundled.') }}</p>
        </div>
        <div class="md:ml-auto inline-flex items-center gap-2 bg-soft rounded-full px-4 h-8 text-[11px] text-muted self-start md:self-auto">
            <i data-lucide="shield-check" class="w-3.5 h-3.5"></i> Includes Crew, Setup &amp; On-Site Sound Engineer
        </div>
    </div>

    <div class="flex gap-2 overflow-x-auto scrollbar-none mt-6 pb-1">
        <a href="{{ route('packages') }}" class="shrink-0 h-9 px-4 rounded-full border text-[12px] font-semibold flex items-center gap-2 {{ ! $active ? 'bg-ink text-white border-ink' : 'border-line hover:bg-soft' }}">
            <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i> All Packages ({{ $total }})
        </a>
        @foreach ($packageCategories as $c)
            <a href="{{ route('packages', ['type' => $c->slug]) }}" class="shrink-0 h-9 px-4 rounded-full border text-[12px] font-semibold flex items-center gap-2 {{ $active === $c->slug ? 'bg-ink text-white border-ink' : 'border-line hover:bg-soft' }}">
                <i data-lucide="{{ $c->icon ?: 'tag' }}" class="w-3.5 h-3.5"></i> {{ $c->name }}
            </a>
        @endforeach
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5 mt-8">
        @forelse ($packages as $package)
            @include('partials.ui', ['ui' => 'package', 'variant' => 'full'])
        @empty
            <div class="col-span-full text-center text-muted py-16">Belum ada paket di kategori ini.</div>
        @endforelse
    </div>

    {{-- Highlight --}}
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 bg-white border border-line rounded-2xl px-6 py-5 mt-10">
        @foreach ([
            ['users-round', '2 Certified', 'Crew members included'],
            ['truck', 'Zero Hassle', 'Logistics & Rigging'],
            ['shield-half', 'Hardware Insured', 'Rp 1B production liability'],
            ['clock-3', 'On-time Guarantee', 'Ready 2 hrs before doors'],
        ] as [$icon, $t, $s])
            <div class="flex items-center gap-3">
                <span class="grid place-items-center w-10 h-10 rounded-full bg-soft"><i data-lucide="{{ $icon }}" class="w-4 h-4"></i></span>
                <div><div class="text-[14px] font-semibold">{{ $t }}</div><div class="text-[11px] text-muted">{{ $s }}</div></div>
            </div>
        @endforeach
    </div>

    {{-- CTA --}}
    <div class="mt-8 rounded-2xl bg-ink text-white px-6 sm:px-9 py-9 flex flex-col lg:flex-row lg:items-center gap-6 relative overflow-hidden">
        <div class="absolute -right-24 -bottom-24 w-72 h-72 rounded-full bg-white/5"></div>
        <div class="relative">
            <span class="inline-flex items-center gap-1.5 bg-white/10 rounded-full px-3 py-1 eyebrow"><i data-lucide="circle-plus" class="w-3 h-3"></i> Enterprise &amp; Custom Arenas</span>
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mt-3">{{ setting('cta_title', 'Need a Custom Turnkey Setup?') }}</h2>
            <p class="text-white/70 text-[13px] mt-2 max-w-xl">{{ setting('cta_text', 'Admin-curated packages with certified technical crew, sound engineers, acoustic modeling, and logistics transport included for stadium or multi-day conventions.') }}</p>
        </div>
        <div class="relative lg:ml-auto flex flex-wrap gap-3">
            @include('partials.ui', ['ui' => 'tech-specs', 'class' => 'h-11 px-6 rounded-full bg-white text-ink text-[12px] font-bold flex items-center gap-2'])
            <a href="https://wa.me/{{ setting('whatsapp', '6281234567890') }}?text={{ urlencode('Halo NEXA, saya butuh custom turnkey setup.') }}" target="_blank" class="h-11 px-6 rounded-full bg-white/15 hover:bg-white/25 text-[12px] font-bold flex items-center gap-2"><i data-lucide="message-square" class="w-4 h-4"></i> Contact Ops</a>
        </div>
    </div>
</div>
@endsection
