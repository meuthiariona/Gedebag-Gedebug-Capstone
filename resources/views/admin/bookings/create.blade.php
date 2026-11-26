@extends('layouts.app')
@section('title', 'Booking manual')

@section('content')
@php
    $input = 'w-full h-10 rounded-xl border border-line px-3 text-sm';
    $itemRows = old('items');
    $itemRows = is_array($itemRows) && $itemRows
        ? array_values(array_filter($itemRows, 'is_array'))
        : [['selection' => '', 'qty' => 1]];
    $nextRow = count($itemRows);
@endphp
<div class="max-w-3xl">
    <a href="{{ route('admin.bookings.index') }}" class="text-sm text-muted">← Daftar booking</a>
    <h1 class="text-2xl font-semibold mt-3 mb-4">Booking manual</h1>
    <form method="POST" action="{{ route('admin.bookings.store') }}" class="bg-white border border-line rounded-2xl p-5 space-y-5">
        @csrf
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach ([
                ['customer_name', 'Nama customer', 'text', true], ['customer_phone', 'Nomor WhatsApp', 'tel', true],
                ['customer_email', 'Email untuk invoice', 'email', false], ['customer_company', 'Perusahaan', 'text', false],
                ['event_name', 'Nama event', 'text', true], ['venue_name', 'Venue', 'text', true],
                ['start_date', 'Tanggal mulai sewa', 'date', true], ['end_date', 'Tanggal selesai sewa', 'date', true],
                ['venue_address', 'Alamat venue', 'text', true], ['venue_note', 'Catatan venue', 'text', false],
                ['pic_name', 'Nama PIC lokasi', 'text', true], ['pic_phone', 'Nomor PIC lokasi', 'tel', true],
            ] as [$name, $label, $type, $required])
                <label class="block text-sm font-medium">
                    {{ $label }}
                    <input name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" class="{{ $input }} mt-1" @required($required)>
                </label>
            @endforeach
        </div>
        <div>
            <h2 class="font-semibold mb-2">Barang atau paket</h2>
            <div id="booking-items" class="space-y-2">
                @foreach ($itemRows as $i => $row)
                    @include('admin.bookings.manual-item', ['index' => $i, 'row' => $row])
                @endforeach
            </div>
            <template id="booking-item-template">
                @include('admin.bookings.manual-item', ['index' => 'INDEX', 'row' => ['selection' => '', 'qty' => 1]])
            </template>
            <button type="button" id="add-booking-item" class="py-2 text-sm font-semibold">+ Tambah item</button>
        </div>
        <label class="block text-sm font-medium">Catatan
            <textarea name="notes" class="w-full rounded-xl border border-line p-3 mt-1">{{ old('notes') }}</textarea>
        </label>
        <button class="rounded-full bg-ink text-white px-5 py-2 text-sm font-semibold">Simpan & tahan stok</button>
    </form>
</div>
<script>
    const rows = document.getElementById('booking-items');
    let nextRow = @json($nextRow);
    document.getElementById('add-booking-item').addEventListener('click', () => {
        if (rows.children.length >= 50) return;
        const template = document.getElementById('booking-item-template');
        rows.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('INDEX', nextRow++));
    });
    rows.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-item]');
        if (remove && rows.children.length > 1) remove.closest('[data-booking-item]').remove();
    });
</script>
@endsection
