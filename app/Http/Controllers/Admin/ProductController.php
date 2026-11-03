<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;

class ProductController extends ResourceController
{
    protected string $model = Product::class;

    protected string $route = 'products';

    protected string $title = 'Produk & Talent';

    protected string $singular = 'Produk';

    protected ?string $slugFrom = 'name';

    protected array $with = ['category'];

    protected array $searchable = ['name', 'subtitle'];

    protected function columns(): array
    {
        return [
            'Foto' => 'img:image_url',
            'Nama' => 'name',
            'Kategori' => fn ($p) => $p->category?->name,
            'Harga' => fn ($p) => rupiah($p->price).' / '.$p->unit,
            'Stok' => 'stock',
            'Aktif' => fn ($p) => $p->is_active ? 'Ya' : 'Tidak',
        ];
    }

    protected function fields(?Model $item = null): array
    {
        return [
            ['name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'options' => Category::orderBy('sort_order')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:categories,id']],
            ['name' => 'name', 'label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'max:150']],
            ['name' => 'subtitle', 'label' => 'Sub-judul (spesifikasi singkat)', 'type' => 'text', 'rules' => ['nullable', 'max:150']],
            ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea'],
            ['name' => 'price', 'label' => 'Harga (Rp)', 'type' => 'money', 'rules' => ['required']],
            ['name' => 'unit', 'label' => 'Satuan harga', 'type' => 'select', 'options' => ['hari' => '/hari', 'sesi' => '/sesi', 'event' => '/event'], 'rules' => ['required']],
            ['name' => 'stock', 'label' => 'Jumlah unit tersedia (stok)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0']],
            ['name' => 'image', 'label' => 'Foto', 'type' => 'image'],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'integer']],
            ['name' => 'is_active', 'label' => 'Tampilkan di website', 'type' => 'checkbox'],
        ];
    }
}
