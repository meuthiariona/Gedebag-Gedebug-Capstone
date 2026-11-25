<div class="flex bg-white border border-line rounded-full p-1 text-[12px] font-semibold">
    <a href="{{ route('admin.bookings.index') }}" class="px-3 py-1.5 rounded-full flex items-center gap-1.5 {{ request()->routeIs('admin.bookings.index') ? 'bg-ink text-white' : '' }}"><i data-lucide="list" class="w-3.5 h-3.5"></i> Daftar</a>
    <a href="{{ route('admin.bookings.calendar') }}" class="px-3 py-1.5 rounded-full flex items-center gap-1.5 {{ request()->routeIs('admin.bookings.calendar') ? 'bg-ink text-white' : '' }}"><i data-lucide="calendar-days" class="w-3.5 h-3.5"></i> Kalender</a>
</div>
