{{-- Komponen kecil yang dipakai ulang. Pakai: @include('partials.ui', ['ui' => 'logo|tech-specs|badges|product|package', ...]) --}}
@switch($ui)
@case('logo')
    <a href="{{ route('home') }}" class="flex items-center gap-2 shrink-0">
        @if (setting('logo'))
            <img src="{{ media_url(setting('logo')) }}" alt="{{ setting('site_name', 'NEXA') }}" class="h-7 w-auto">
        @else
            <span class="grid place-items-center w-7 h-7 rounded-md bg-ink text-white"><i data-lucide="audio-lines" class="w-4 h-4"></i></span>
        @endif
        <span class="font-extrabold tracking-tight text-[17px]">{{ setting('site_name', 'NEXA') }}</span>
    </a>
    @break

@case('tech-specs'){{-- Variabel: $class --}}
    @if (setting('tech_specs_file'))
        <a href="{{ media_url(setting('tech_specs_file')) }}" target="_blank" download class="{{ $class }}"><i data-lucide="download" class="w-4 h-4"></i> Download Tech Specs</a>
    @else
        <a href="mailto:{{ setting('support_email', 'ops@nexaevents.io') }}?subject={{ urlencode('Request Tech Specs') }}" class="{{ $class }}"><i data-lucide="download" class="w-4 h-4"></i> Download Tech Specs</a>
    @endif
    @break

@case('badges'){{-- Badge status booking. Variabel: $booking --}}
    <span class="text-[10px] font-bold tracking-[.1em] text-muted">#{{ $booking->code }}</span>
    @php($statusCls = ['confirmed' => 'bg-ink text-white', 'pending' => 'bg-soft text-ink', 'completed' => 'bg-emerald-600 text-white', 'cancelled' => 'bg-red-100 text-red-700'][$booking->status])
    <span class="inline-flex items-center gap-1 text-[9.5px] font-bold tracking-[.08em] uppercase px-2 py-0.5 rounded {{ $statusCls }}">
        <span class="w-1.5 h-1.5 rounded-full {{ $booking->status === 'confirmed' ? 'bg-emerald-400' : 'bg-current' }}"></span>{{ \App\Models\Booking::STATUSES[$booking->status] }}
    </span>
    @if ($booking->status !== 'cancelled')
        <span class="text-[9.5px] font-bold tracking-[.08em] uppercase px-2 py-0.5 rounded {{ $booking->payment_status === 'dp_paid' ? 'bg-blue-50 text-blue-700' : 'bg-soft' }}">{{ $booking->payment_label }}</span>
    @endif
    @break

@case('product'){{-- Kartu produk / talent. Variabel: $product --}}
    @php($isPhoto = $product->category?->card_style === 'photo')
    <div class="group bg-white border border-line rounded-xl p-2.5 shadow-card flex flex-col">
        <div class="relative aspect-[4/3] rounded-lg overflow-hidden {{ $isPhoto ? 'bg-ink' : 'bg-soft' }}">
            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy"
                 class="w-full h-full {{ $isPhoto ? 'object-cover' : 'object-contain p-3 mix-blend-multiply' }} group-hover:scale-[1.03] transition duration-500">
            <button type="button" onclick="toggleWish(this, '{{ route('wishlist.toggle', ['product', $product->id]) }}')"
                    class="heart {{ in_array('product-'.$product->id, $wishKeys ?? []) ? 'is-saved' : '' }} absolute top-2 right-2 grid place-items-center w-7 h-7 rounded-full bg-white/90 border border-line shadow-sm">
                <i data-lucide="heart" class="w-3.5 h-3.5"></i>
            </button>
        </div>
        <div class="px-1.5 pt-3 pb-1 flex-1 flex flex-col">
            <h3 class="text-[13px] font-semibold leading-tight">{{ $product->name }}</h3>
            <p class="text-[11px] text-muted mt-0.5 line-clamp-1">{{ $product->subtitle }}</p>
            <div class="flex items-end justify-between mt-auto pt-3">
                <div>
                    <div class="text-[14px] font-bold">{{ rupiah($product->price) }}</div>
                    <div class="text-[10px] text-muted">/{{ $product->unit }}</div>
                </div>
                <form action="{{ route('cart.product', $product) }}" method="POST">@csrf
                    <button class="grid place-items-center w-8 h-8 rounded-full bg-ink text-white hover:scale-105 transition" title="Tambah ke keranjang">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    @break

@case('package'){{-- Kartu paket. Variabel: $package, $variant ('compact' beranda / 'full' halaman paket) --}}
    @php($variant = $variant ?? 'full')
    @php($saved = in_array('package-'.$package->id, $wishKeys ?? []))
    @if ($variant === 'compact')
        <div class="group bg-white border border-line rounded-xl p-2.5 shadow-card flex flex-col">
            <a href="{{ route('packages.show', $package->slug) }}" class="relative block aspect-[4/3] rounded-lg overflow-hidden bg-ink">
                <img src="{{ $package->image_url }}" alt="{{ $package->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-500">
                <button type="button" onclick="event.preventDefault(); toggleWish(this, '{{ route('wishlist.toggle', ['package', $package->id]) }}')"
                        class="heart {{ $saved ? 'is-saved' : '' }} absolute top-2 right-2 grid place-items-center w-7 h-7 rounded-full bg-white/90 shadow-sm">
                    <i data-lucide="heart" class="w-3.5 h-3.5"></i>
                </button>
            </a>
            <div class="px-1.5 pt-3 pb-1 flex-1 flex flex-col">
                <a href="{{ route('packages.show', $package->slug) }}" class="text-[13px] font-semibold leading-tight hover:underline">{{ $package->name }}</a>
                <p class="text-[11px] text-muted mt-0.5 line-clamp-1">{{ $package->summary }}</p>
                <div class="flex items-end justify-between mt-auto pt-3">
                    <div>
                        <div class="text-[14px] font-bold">{{ rupiah($package->price) }}</div>
                        <div class="text-[10px] text-muted">/{{ $package->unit }}</div>
                    </div>
                    <form action="{{ route('cart.package', $package) }}" method="POST">@csrf
                        <button class="grid place-items-center w-8 h-8 rounded-full bg-ink text-white hover:scale-105 transition"><i data-lucide="plus" class="w-4 h-4"></i></button>
                    </form>
                </div>
            </div>
        </div>
    @else
        <div class="group bg-white border border-line rounded-2xl overflow-hidden shadow-card flex flex-col">
            <a href="{{ route('packages.show', $package->slug) }}" class="relative block aspect-[4/3] overflow-hidden bg-ink">
                <img src="{{ $package->image_url }}" alt="{{ $package->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-500">
                @if ($package->badge)
                    <span class="absolute left-3 bottom-3 bg-ink/85 text-white text-[10px] font-bold tracking-[.12em] uppercase px-2.5 py-1 rounded">{{ $package->badge }}</span>
                @endif
                <button type="button" onclick="event.preventDefault(); toggleWish(this, '{{ route('wishlist.toggle', ['package', $package->id]) }}')"
                        class="heart {{ $saved ? 'is-saved' : '' }} absolute top-3 right-3 grid place-items-center w-9 h-9 rounded-full bg-white/90 shadow-sm">
                    <i data-lucide="heart" class="w-4 h-4"></i>
                </button>
            </a>
            <div class="p-4 flex-1 flex flex-col">
                <a href="{{ route('packages.show', $package->slug) }}" class="text-[15px] font-semibold leading-snug hover:underline">{{ $package->name }}</a>
                <p class="text-[11.5px] text-muted mt-1 line-clamp-2">{{ $package->summary }}</p>
                <div class="flex items-center gap-1.5 text-[10.5px] font-semibold mt-4 tracking-wide">
                    <span class="w-1.5 h-1.5 rounded-full {{ ($package->available ?? 1) > 0 ? 'bg-emerald-500' : 'bg-red-500' }}"></span>
                    {{ $package->available ?? 0 }} {{ Str::plural('package', $package->available ?? 0) }} available for {{ rental_range_label() }}
                </div>
                <div class="flex items-end justify-between mt-2">
                    <div>
                        <div class="text-[15px] font-bold">{{ rupiah($package->price) }}</div>
                        <div class="text-[10.5px] text-muted">/ {{ $package->unit }}</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('packages.show', $package->slug) }}" class="text-[12px] font-semibold hover:underline">Details</a>
                        <form action="{{ route('cart.package', $package) }}" method="POST">@csrf
                            <button class="grid place-items-center w-9 h-9 rounded-full bg-ink text-white hover:scale-105 transition" @disabled(($package->available ?? 1) < 1)><i data-lucide="plus" class="w-4 h-4"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
    @break
@endswitch
