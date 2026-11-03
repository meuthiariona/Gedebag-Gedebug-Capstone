@extends('layouts.app')
@section('title', $title)

@section('content')
<div class="flex flex-wrap items-center gap-3 mb-4">
    <form method="GET" class="flex items-center gap-2 bg-white border border-line rounded-full px-4 h-10 w-full sm:w-72">
        <i data-lucide="search" class="w-4 h-4 text-muted"></i>
        <input name="q" value="{{ $search }}" placeholder="Cari..." class="text-sm outline-none w-full">
    </form>
    <a href="{{ route("admin.$route.create") }}" class="ml-auto h-10 px-5 rounded-full bg-ink text-white text-[13px] font-bold flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Tambah {{ $singular }}</a>
</div>

<div class="bg-white border border-line rounded-2xl overflow-x-auto">
    <table class="w-full text-[13px]">
        <thead class="bg-soft/60 text-left text-[11px] uppercase tracking-wider text-muted">
            <tr>
                @foreach ($columns as $label => $attr)<th class="px-4 py-3 font-semibold">{{ $label }}</th>@endforeach
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @forelse ($items as $item)
                <tr class="hover:bg-soft/40">
                    @foreach ($columns as $label => $attr)
                        <td class="px-4 py-3">
                            @if (is_string($attr) && str_starts_with($attr, 'img:'))
                                <img src="{{ $item->{substr($attr, 4)} }}" class="w-12 h-12 rounded-lg object-cover bg-soft">
                            @else
                                {{ $attr instanceof Closure ? $attr($item) : $item->{$attr} }}
                            @endif
                        </td>
                    @endforeach
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route("admin.$route.edit", $item->id) }}" class="inline-flex items-center gap-1 h-8 px-3 rounded-full bg-soft text-[12px] font-semibold"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</a>
                        <form action="{{ route("admin.$route.destroy", $item->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus data ini?')">@csrf @method('DELETE')
                            <button class="inline-flex items-center h-8 px-3 rounded-full text-red-600 hover:bg-red-50 text-[12px]"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) + 1 }}" class="px-4 py-10 text-center text-muted">Belum ada data.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $items->links() }}</div>
@endsection
