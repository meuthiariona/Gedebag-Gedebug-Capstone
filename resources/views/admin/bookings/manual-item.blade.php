<div class="grid grid-cols-[minmax(0,1fr)_5rem_auto] gap-2 items-center" data-booking-item>
    <select name="items[{{ $index }}][selection]" aria-label="Barang atau paket" class="{{ $input }}" required>
        <option value="">Pilih item</option>
        <optgroup label="Barang dan talent">
            @foreach ($products as $product)
                <option value="product:{{ $product->id }}" @selected(($row['selection'] ?? '') === 'product:'.$product->id)>{{ $product->name }}</option>
            @endforeach
        </optgroup>
        <optgroup label="Paket">
            @foreach ($packages as $package)
                <option value="package:{{ $package->id }}" @selected(($row['selection'] ?? '') === 'package:'.$package->id)>{{ $package->name }}</option>
            @endforeach
        </optgroup>
    </select>
    <input type="number" name="items[{{ $index }}][qty]" value="{{ $row['qty'] ?? 1 }}" min="1" max="10000" required aria-label="Jumlah" class="{{ $input }}">
    <button type="button" data-remove-item class="text-red-700 text-sm">Hapus</button>
</div>
