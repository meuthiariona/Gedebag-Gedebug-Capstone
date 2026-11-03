@extends('layouts.app')
@section('title', 'Pengaturan Website')

@section('content')
<form action="{{ route('admin.settings') }}" method="POST" enctype="multipart/form-data" class="space-y-5 max-w-4xl">
    @csrf
    @foreach ($groups as $group => $fields)
        <div class="bg-white border border-line rounded-2xl p-6">
            <h2 class="font-semibold mb-4">{{ $group }}</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ($fields as $key => [$label, $type])
                    <div class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
                        <label class="text-[12px] font-semibold">{{ $label }}</label>
                        @if ($type === 'textarea')
                            <textarea name="{{ $key }}" rows="3" class="w-full px-4 py-3 rounded-xl border border-line text-sm mt-1 outline-none focus:border-ink">{{ $values[$key] ?? '' }}</textarea>
                        @elseif (in_array($type, ['image', 'file']))
                            <div class="flex items-center gap-3 mt-1">
                                @if (! empty($values[$key]))
                                    @if ($type === 'image')<img src="{{ media_url($values[$key]) }}" class="h-12 rounded bg-soft">@else<a href="{{ media_url($values[$key]) }}" target="_blank" class="text-[12px] underline">File saat ini</a>@endif
                                @endif
                                <input type="file" name="{{ $key }}" class="text-[12px] file:mr-3 file:h-9 file:px-4 file:rounded-full file:border-0 file:bg-ink file:text-white file:font-semibold">
                            </div>
                        @else
                            <input type="{{ $type === 'number' ? 'number' : 'text' }}" name="{{ $key }}" value="{{ $values[$key] ?? '' }}" class="w-full h-11 px-4 rounded-xl border border-line text-sm mt-1 outline-none focus:border-ink">
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    <button class="h-11 px-8 rounded-full bg-ink text-white text-[13px] font-bold">Simpan Pengaturan</button>
</form>
@endsection
