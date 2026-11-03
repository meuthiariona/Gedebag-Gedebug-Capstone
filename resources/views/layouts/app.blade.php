{{-- Satu layout untuk website publik & panel admin (/admin/*). --}}
@php $isAdmin = request()->routeIs('admin.*'); @endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($isAdmin)
        <title>@yield('title', 'Dashboard') · Admin {{ setting('site_name', 'NEXA') }}</title>
    @else
        <title>@yield('title', 'Home') · {{ setting('site_name', 'NEXA') }} Event Production</title>
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Manrope', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        ink: '#0b0b0c',
                        muted: '#6b6f76',
                        line: '#e8e9ec',
                        soft: '#f4f5f7',
                    },
                    boxShadow: { card: '0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.06)' },
                },
            },
        };
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@0.454.0/dist/umd/lucide.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
        .heart.is-saved svg { fill: #ef4444; stroke: #ef4444; }
        .eyebrow { font-size: 10px; letter-spacing: .14em; font-weight: 700; text-transform: uppercase; }
        .scrollbar-none::-webkit-scrollbar { display: none; }
        .scrollbar-none { scrollbar-width: none; }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
@if ($isAdmin)
<body class="bg-soft text-ink font-sans antialiased" x-data="{ side: false }">
@php
    $menu = [
        ['Dashboard', 'admin.dashboard', 'layout-dashboard', 'admin.dashboard'],
        ['Booking', 'admin.bookings.index', 'calendar-check', 'admin.bookings.*'],
        ['Produk & Talent', 'admin.products.index', 'speaker', 'admin.products.*'],
        ['Kategori Produk', 'admin.categories.index', 'folder', 'admin.categories.*'],
        ['Paket Event', 'admin.packages.index', 'package', 'admin.packages.*'],
        ['Kategori Paket', 'admin.package-categories.index', 'tags', 'admin.package-categories.*'],
        ['Banner Beranda', 'admin.hero-slides.index', 'image', 'admin.hero-slides.*'],
        ['Pengguna', 'admin.users.index', 'users', 'admin.users.*'],
        ['Pengaturan', 'admin.settings', 'settings', 'admin.settings'],
    ];
@endphp
<div class="flex min-h-screen">
    <aside :class="side ? 'translate-x-0' : '-translate-x-full'" class="fixed lg:sticky top-0 z-40 h-screen w-60 bg-ink text-white p-4 flex flex-col transition lg:translate-x-0">
        <div class="flex items-center gap-2 px-2 py-2">
            <span class="grid place-items-center w-7 h-7 rounded-md bg-white text-ink"><i data-lucide="audio-lines" class="w-4 h-4"></i></span>
            <span class="font-extrabold">{{ setting('site_name', 'NEXA') }} <span class="font-normal text-white/50 text-sm">Admin</span></span>
        </div>
        <nav class="mt-6 space-y-0.5 flex-1">
            @foreach ($menu as [$label, $route, $icon, $pattern])
                <a href="{{ route($route) }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] {{ request()->routeIs($pattern) ? 'bg-white text-ink font-semibold' : 'text-white/75 hover:bg-white/10' }}">
                    <i data-lucide="{{ $icon }}" class="w-4 h-4"></i> {{ $label }}
                </a>
            @endforeach
        </nav>
        <a href="{{ route('home') }}" target="_blank" class="flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] text-white/75 hover:bg-white/10"><i data-lucide="external-link" class="w-4 h-4"></i> Lihat website</a>
        <form action="{{ route('logout') }}" method="POST">@csrf
            <button class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[13px] text-white/75 hover:bg-white/10"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</button>
        </form>
    </aside>
    <div x-show="side" @click="side = false" class="fixed inset-0 bg-black/40 z-30 lg:hidden" x-cloak></div>

    <div class="flex-1 min-w-0">
        <header class="bg-white border-b border-line h-14 px-4 sm:px-6 flex items-center gap-3 sticky top-0 z-20">
            <button class="lg:hidden" @click="side = true"><i data-lucide="menu" class="w-5 h-5"></i></button>
            <h1 class="font-semibold">@yield('title', 'Dashboard')</h1>
            <span class="ml-auto text-[12px] text-muted">{{ auth()->user()->name }}</span>
        </header>
        <main class="p-4 sm:p-6">
            @if (session('success'))
                <div class="bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-xl px-4 py-3 text-sm mb-4">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 text-sm mb-4">@foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script>document.addEventListener('DOMContentLoaded', () => lucide.createIcons());</script>
</body>
@else
<body class="bg-white text-ink font-sans antialiased">
    {{-- ===== HEADER ===== --}}
    @php
        $nav = [
            ['Home', route('home'), request()->routeIs('home')],
            ['Products', route('products'), request()->routeIs('products')],
            ['Packages', route('packages'), request()->routeIs('packages*')],
            ['My Bookings', route('bookings.index'), request()->routeIs('bookings.*')],
        ];
    @endphp
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-line no-print" x-data="{ open: false, menu: false }">
        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 h-16 flex items-center gap-6">
            @include('partials.ui', ['ui' => 'logo'])

            <nav class="hidden md:flex items-center gap-1 mx-auto">
                @foreach ($nav as [$label, $url, $active])
                    <a href="{{ $url }}" class="px-4 py-1.5 rounded-full text-[13px] font-semibold transition {{ $active ? 'bg-ink text-white' : 'text-ink/80 hover:bg-soft' }}">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="flex items-center gap-3 ml-auto md:ml-0">
                <form action="{{ route('products') }}" method="GET" class="hidden lg:flex items-center gap-2 bg-soft rounded-full px-3.5 h-9 w-56">
                    <i data-lucide="search" class="w-4 h-4 text-muted"></i>
                    <input name="q" value="{{ request('q') }}" placeholder="Search gear, audio, stages..." class="bg-transparent text-[12px] outline-none w-full placeholder:text-muted">
                </form>

                <a href="{{ route('cart.index') }}" class="relative grid place-items-center w-9 h-9 rounded-full hover:bg-soft" title="Keranjang">
                    <i data-lucide="shopping-bag" class="w-[18px] h-[18px]"></i>
                    @if ($cartCount ?? 0)
                        <span class="absolute -top-0.5 -right-0.5 min-w-[17px] h-[17px] px-1 rounded-full bg-ink text-white text-[10px] font-bold grid place-items-center">{{ $cartCount }}</span>
                    @endif
                </a>

                <div class="relative" @click.outside="menu = false">
                    <button @click="menu = !menu" class="grid place-items-center w-9 h-9 rounded-full bg-ink text-white" title="Akun">
                        <i data-lucide="user" class="w-4 h-4"></i>
                    </button>
                    <div x-cloak x-show="menu" x-transition class="absolute right-0 mt-2 w-56 bg-white border border-line rounded-xl shadow-lg p-2 text-sm">
                        @auth
                            <div class="px-3 py-2 border-b border-line mb-1">
                                <div class="font-bold">{{ auth()->user()->name }}</div>
                                <div class="text-xs text-muted truncate">{{ auth()->user()->email }}</div>
                            </div>
                            @if (auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-soft"><i data-lucide="layout-dashboard" class="w-4 h-4"></i> Admin Panel</a>
                            @endif
                            <a href="{{ route('bookings.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-soft"><i data-lucide="calendar-check" class="w-4 h-4"></i> My Bookings</a>
                            <a href="{{ route('wishlist') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-soft"><i data-lucide="heart" class="w-4 h-4"></i> Wishlist</a>
                            <form action="{{ route('logout') }}" method="POST">@csrf
                                <button class="w-full flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-soft text-red-600"><i data-lucide="log-out" class="w-4 h-4"></i> Logout</button>
                            </form>
                        @else
                            <a href="{{ route('login') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-soft"><i data-lucide="log-in" class="w-4 h-4"></i> Login</a>
                            <a href="{{ route('register') }}" class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-soft"><i data-lucide="user-plus" class="w-4 h-4"></i> Daftar akun</a>
                        @endauth
                    </div>
                </div>

                <button class="md:hidden grid place-items-center w-9 h-9 rounded-full hover:bg-soft" @click="open = !open"><i data-lucide="menu" class="w-5 h-5"></i></button>
            </div>
        </div>
        <nav x-cloak x-show="open" class="md:hidden border-t border-line px-4 py-3 space-y-1">
            <form action="{{ route('products') }}" method="GET" class="flex items-center gap-2 bg-soft rounded-full px-3.5 h-10 mb-2">
                <i data-lucide="search" class="w-4 h-4 text-muted"></i>
                <input name="q" placeholder="Search gear, audio, stages..." class="bg-transparent text-sm outline-none w-full">
            </form>
            @foreach ($nav as [$label, $url, $active])
                <a href="{{ $url }}" class="block px-4 py-2 rounded-lg text-sm font-semibold {{ $active ? 'bg-ink text-white' : 'hover:bg-soft' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </header>

    {{-- ===== SUB BAR: bisa diganti lewat @section('subbar'), atau $bar = 'dispatch' untuk halaman booking ===== --}}
    @if ($__env->hasSection('subbar'))
        @yield('subbar')
    @elseif (($bar ?? null) === 'dispatch')
        <div class="bg-soft/70 border-b border-line no-print">
            <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-3 text-[10.5px]">
                <span class="flex items-center gap-2 font-bold tracking-[.08em] uppercase"><span class="w-1.5 h-1.5 rounded-full bg-ink"></span> {{ setting('dispatch_hub', 'Fleet Dispatch: Regional Hub Bandung (West Java) Online') }}</span>
                <div class="ml-auto flex items-center gap-4 text-muted">
                    <span class="flex items-center gap-1.5"><i data-lucide="circle-check" class="w-3.5 h-3.5"></i> Insurance Coverage Active</span>
                    <span class="flex items-center gap-1.5"><i data-lucide="clock" class="w-3.5 h-3.5"></i> Rider Changes Cutoff: 48h prior</span>
                </div>
            </div>
        </div>
    @else
        @php [$rs, $re] = rental_dates(); @endphp
        <div class="bg-soft/70 border-b border-line no-print">
            <form action="{{ route('availability') }}" method="POST" class="max-w-[1180px] mx-auto px-4 sm:px-6 py-2.5 flex flex-wrap items-center gap-3">
                @csrf
                <div class="flex items-center gap-2 text-[11px]">
                    <i data-lucide="calendar-days" class="w-4 h-4"></i>
                    <span class="font-bold tracking-[.08em] uppercase">Event Availability Engine</span>
                    <span class="hidden sm:inline text-muted">• Real-time equipment dispatch &amp; logistics check</span>
                </div>
                <div class="ml-auto flex flex-wrap items-center gap-2">
                    <div class="flex items-center bg-white border border-line rounded-full px-3 h-9 text-[11px] gap-2">
                        <label class="font-semibold uppercase tracking-wider text-muted">Start:</label>
                        <input type="date" name="start" value="{{ $rs->toDateString() }}" min="{{ today()->toDateString() }}" class="font-semibold outline-none bg-transparent">
                        <i data-lucide="arrow-right" class="w-3.5 h-3.5 text-muted"></i>
                        <label class="font-semibold uppercase tracking-wider text-muted">End:</label>
                        <input type="date" name="end" value="{{ $re->toDateString() }}" min="{{ today()->toDateString() }}" class="font-semibold outline-none bg-transparent">
                    </div>
                    <button class="h-9 px-4 rounded-full bg-ink text-white text-[12px] font-bold flex items-center gap-2">
                        Check Availability <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- ===== FLASH MESSAGE ===== --}}
    @foreach (['success' => 'bg-ink text-white', 'error' => 'bg-red-600 text-white'] as $key => $cls)
        @if (session($key))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)" x-show="show" x-transition
                 class="fixed bottom-5 right-5 z-50 max-w-sm {{ $cls }} rounded-xl shadow-xl px-4 py-3 text-sm flex items-start gap-3 no-print">
                <i data-lucide="{{ $key === 'success' ? 'circle-check' : 'circle-alert' }}" class="w-5 h-5 shrink-0"></i>
                <span>{{ session($key) }}</span>
                <button @click="show = false" class="ml-2 opacity-70 hover:opacity-100"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
        @endif
    @endforeach
    @if ($errors->any() && ! request()->routeIs('login', 'register', 'checkout'))
        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 mt-4">
            <div class="bg-red-50 text-red-700 border border-red-200 rounded-xl px-4 py-3 text-sm">{{ $errors->first() }}</div>
        </div>
    @endif

    <main>
        @yield('content')
    </main>

    {{-- ===== FOOTER ===== --}}
    <footer class="bg-soft/80 border-t border-line mt-20 no-print">
        <div class="max-w-[1180px] mx-auto px-4 sm:px-6 pt-14 pb-8">
            <div class="grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    @include('partials.ui', ['ui' => 'logo'])
                    <p class="text-[12px] text-muted leading-relaxed mt-3 max-w-[240px]">{{ setting('tagline', 'Industrial-grade event hardware, live stage production, and premier entertainment bookings on single frictionless rails.') }}</p>
                </div>
                <div>
                    <div class="eyebrow mb-3">Catalog</div>
                    <ul class="space-y-1.5 text-[12px] text-muted">
                        @foreach ($footerCategories as $c)
                            <li><a href="{{ route('products', ['category' => $c->slug]) }}" class="hover:text-ink">{{ $c->name }}</a></li>
                        @endforeach
                        <li><a href="{{ route('packages') }}" class="hover:text-ink">Event Packages</a></li>
                    </ul>
                </div>
                <div>
                    <div class="eyebrow mb-3">Operations</div>
                    <ul class="space-y-1.5 text-[12px] text-muted">
                        <li>Rental Policy &amp; Logistics</li>
                        <li>On-site Engineers</li>
                        <li>Equipment Insurance</li>
                        <li>Enterprise SLA</li>
                    </ul>
                </div>
                <div>
                    <div class="eyebrow mb-3">Direct Support</div>
                    <ul class="space-y-1.5 text-[12px]">
                        <li class="text-muted">{{ setting('support_region', 'Jakarta & Bali Regional Dispatch') }}</li>
                        <li><a href="mailto:{{ setting('support_email', 'ops@nexaevents.io') }}" class="font-semibold">{{ setting('support_email', 'ops@nexaevents.io') }}</a></li>
                        <li class="font-semibold">{{ setting('support_phone', '+62 (21) 5092-8812') }}</li>
                    </ul>
                </div>
            </div>
            <div class="flex flex-wrap justify-between gap-4 text-[11px] text-muted mt-14">
                <span>© {{ date('Y') }} {{ setting('company_name', 'NEXA Live Production & Equipment Ltd.') }} All rights reserved.</span>
                <div class="flex gap-5"><span>Terms of Rental</span><span>Privacy Statement</span><span>Safety Certifications</span></div>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', () => window.lucide && lucide.createIcons());
        // Toggle wishlist tanpa reload halaman
        async function toggleWish(btn, url) {
            const res = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' } });
            if (res.status === 401) { window.location = '{{ route('login') }}'; return; }
            const data = await res.json();
            btn.classList.toggle('is-saved', data.saved);
        }
    </script>
    @stack('scripts')
</body>
@endif
</html>
