@extends('layouts.app')
@section('title', ($item->exists ? 'Edit ' : 'Tambah ').$singular)

@section('content')
<a href="{{ route("admin.$route.index") }}" class="text-[12px] text-muted flex items-center gap-1 mb-4"><i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> {{ $title }}</a>

<form action="{{ $item->exists ? route("admin.$route.update", $item->id) : route("admin.$route.store") }}" method="POST" enctype="multipart/form-data"
      class="bg-white border border-line rounded-2xl p-6 grid sm:grid-cols-2 gap-5 max-w-4xl">
    @csrf
    @if ($item->exists) @method('PUT') @endif

    @foreach ($fields as $f)
        @php
            $name = $f['name'];
            $value = old($name, $item->{$name});
            $wide = in_array($f['type'], ['textarea', 'image', 'gallery']);
            $cls = 'w-full h-11 px-4 rounded-xl border border-line text-sm outline-none focus:border-ink mt-1';
        @endphp
        <div class="{{ $wide ? 'sm:col-span-2' : '' }}">
            @if ($f['type'] !== 'checkbox')
                <label class="text-[12px] font-semibold">{{ $f['label'] }} @if(in_array('required', $f['rules'] ?? []))<span class="text-red-500">*</span>@endif</label>
            @endif

            @switch($f['type'])
                @case('textarea')
                    <textarea name="{{ $name }}" rows="4" class="w-full px-4 py-3 rounded-xl border border-line text-sm outline-none focus:border-ink mt-1">{{ $value }}</textarea>
                    @break
                @case('select')
                    <select name="{{ $name }}" class="{{ $cls }} bg-white">
                        <option value="">— pilih —</option>
                        @foreach ($f['options'] as $k => $v)<option value="{{ $k }}" @selected((string) $value === (string) $k)>{{ $v }}</option>@endforeach
                    </select>
                    @break
                @case('checkbox')
                    <label class="flex items-center gap-2 text-sm mt-6"><input type="checkbox" name="{{ $name }}" value="1" class="w-4 h-4 accent-black" @checked($item->exists ? $value : true)> {{ $f['label'] }}</label>
                    @break
                @case('money')
                    <input name="{{ $name }}" value="{{ $value !== null ? number_format((int) $value, 0, ',', '.') : '' }}" inputmode="numeric" class="{{ $cls }}" placeholder="0"
                           oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')">
                    @break
                @case('number')
                    <input type="number" name="{{ $name }}" value="{{ $value }}" class="{{ $cls }}">
                    @break
                @case('password')
                    <input type="password" name="{{ $name }}" class="{{ $cls }}" autocomplete="new-password">
                    @break
                @case('tags')
                    <input name="{{ $name }}" value="{{ is_array($value) ? implode(', ', $value) : $value }}" class="{{ $cls }}">
                    @break
                @case('image')
                    <div class="flex flex-wrap items-start gap-4 mt-2">
                        @if ($item->{$name})
                            <div class="text-center">
                                <img src="{{ media_url($item->{$name}) }}" class="w-32 h-24 object-cover rounded-xl bg-soft border border-line">
                                <label class="text-[11px] text-red-600 flex items-center gap-1 mt-1"><input type="checkbox" name="{{ $name }}_remove" value="1"> hapus</label>
                            </div>
                        @endif
                        <div class="flex-1 min-w-[240px] space-y-2">
                            <input type="file" name="{{ $name }}" accept="image/*" class="block text-[12px] file:mr-3 file:h-9 file:px-4 file:rounded-full file:border-0 file:bg-ink file:text-white file:font-semibold">
                            <input name="{{ $name }}_url" placeholder="…atau tempel URL gambar (https://...)" class="{{ $cls }}">
                        </div>
                    </div>
                    @break
                @case('gallery')
                    @if ($item->{$name})
                        <div class="flex flex-wrap gap-2 mt-2">@foreach ($item->{$name} as $g)<img src="{{ media_url($g) }}" class="w-24 h-16 object-cover rounded-lg bg-soft">@endforeach</div>
                    @endif
                    <textarea name="{{ $name }}" rows="3" class="w-full px-4 py-3 rounded-xl border border-line text-[12px] font-mono outline-none focus:border-ink mt-2" placeholder="Satu path / URL per baris">{{ is_array($value) ? implode("\n", $value) : $value }}</textarea>
                    <div class="text-[11px] text-muted">Hapus baris untuk membuang foto. Upload foto baru:</div>
                    <input type="file" name="{{ $name }}_files[]" multiple accept="image/*" class="block text-[12px] mt-1 file:mr-3 file:h-9 file:px-4 file:rounded-full file:border-0 file:bg-ink file:text-white file:font-semibold">
                    @break
                @default
                    <input type="{{ $f['type'] === 'email' ? 'email' : 'text' }}" name="{{ $name }}" value="{{ $value }}" class="{{ $cls }}">
            @endswitch
        </div>
    @endforeach

    <div class="sm:col-span-2 flex gap-3 pt-2">
        <button class="h-11 px-6 rounded-full bg-ink text-white text-[13px] font-bold">Simpan</button>
        <a href="{{ route("admin.$route.index") }}" class="h-11 px-6 rounded-full bg-soft text-[13px] font-semibold flex items-center">Batal</a>
    </div>
</form>

{{-- Bagian tambahan khusus edit paket: isi paket & add-on --}}
@if ($extra === 'package')
@php $cls = 'h-10 px-3 rounded-xl border border-line text-sm outline-none focus:border-ink'; @endphp
<div class="grid xl:grid-cols-2 gap-5 mt-6 max-w-6xl">
    {{-- Isi paket --}}
    <div class="bg-white border border-line rounded-2xl p-6">
        <h2 class="font-semibold">Isi Paket (hardware, talent, layanan)</h2>
        <div class="divide-y divide-line mt-3">
            @forelse ($item->items as $pi)
                <div class="py-2.5 flex items-center gap-3 text-[13px]">
                    <span class="text-[10px] uppercase font-bold bg-soft rounded px-1.5 py-0.5">{{ $pi->type }}</span>
                    <b>{{ $pi->qty }}×</b>
                    <div class="min-w-0"><div class="font-semibold">{{ $pi->name }}</div><div class="text-[11px] text-muted">{{ $pi->subtitle }} @if($pi->tag) · {{ $pi->tag }} @endif</div></div>
                    <form action="{{ route('admin.packages.items.destroy', $pi) }}" method="POST" class="ml-auto">@csrf @method('DELETE')
                        <button class="text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </div>
            @empty
                <div class="text-muted text-sm py-3">Belum ada item.</div>
            @endforelse
        </div>
        <form action="{{ route('admin.packages.items.store', $item) }}" method="POST" class="grid grid-cols-6 gap-2 mt-4 border-t border-line pt-4">@csrf
            <select name="type" class="{{ $cls }} col-span-2 bg-white">@foreach (\App\Models\PackageItem::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
            <input name="qty" type="number" value="1" min="1" class="{{ $cls }}" title="Qty">
            <input name="name" placeholder="Nama item *" required class="{{ $cls }} col-span-3">
            <input name="subtitle" placeholder="Keterangan (mis. JBL SRX / RCF ART 715)" class="{{ $cls }} col-span-4">
            <input name="tag" placeholder="Tag (FRONT OF HOUSE)" class="{{ $cls }} col-span-2">
            <button class="col-span-6 h-10 rounded-full bg-ink text-white text-[12px] font-bold">+ Tambah item</button>
        </form>
    </div>

    {{-- Add-on --}}
    <div class="bg-white border border-line rounded-2xl p-6">
        <h2 class="font-semibold">Add-on (Customize Your Production)</h2>
        <div class="divide-y divide-line mt-3">
            @forelse ($item->addons as $ad)
                <div class="py-2.5 flex items-center gap-3 text-[13px]">
                    <div class="min-w-0"><div class="font-semibold">{{ $ad->name }}</div><div class="text-[11px] text-muted">{{ $ad->description }}</div></div>
                    <span class="ml-auto font-semibold whitespace-nowrap">+ {{ rupiah($ad->price) }} /{{ $ad->unit }}</span>
                    <form action="{{ route('admin.packages.addons.destroy', $ad) }}" method="POST">@csrf @method('DELETE')
                        <button class="text-red-600"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </div>
            @empty
                <div class="text-muted text-sm py-3">Belum ada add-on.</div>
            @endforelse
        </div>
        <form action="{{ route('admin.packages.addons.store', $item) }}" method="POST" class="grid grid-cols-6 gap-2 mt-4 border-t border-line pt-4">@csrf
            <input name="name" placeholder="Nama add-on *" required class="{{ $cls }} col-span-6">
            <input name="description" placeholder="Deskripsi singkat" class="{{ $cls }} col-span-6">
            <input name="price" placeholder="Harga (Rp) *" required class="{{ $cls }} col-span-4" oninput="this.value = this.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.')">
            <select name="unit" class="{{ $cls }} col-span-2 bg-white"><option value="event">/event</option><option value="sesi">/sesi</option><option value="hari">/hari</option></select>
            <button class="col-span-6 h-10 rounded-full bg-ink text-white text-[12px] font-bold">+ Tambah add-on</button>
        </form>
    </div>
</div>
@endif
@endsection
