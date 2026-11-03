<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Database\Eloquent\Model;

class CategoryController extends ResourceController
{
    protected string $model = Category::class;

    protected string $route = 'categories';

    protected string $title = 'Kategori Produk';

    protected string $singular = 'Kategori';

    protected ?string $slugFrom = 'name';

    protected function columns(): array
    {
        return [
            'Nama' => 'name',
            'Deskripsi' => 'description',
            'Ikon' => 'icon',
            'Produk' => fn ($c) => $c->products()->count(),
            'Urutan' => 'sort_order',
            'Aktif' => fn ($c) => $c->is_active ? 'Ya' : 'Tidak',
        ];
    }

    protected function fields(?Model $item = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama Kategori', 'type' => 'text', 'rules' => ['required', 'max:100']],
            ['name' => 'description', 'label' => 'Deskripsi singkat', 'type' => 'text', 'rules' => ['nullable', 'max:255']],
            ['name' => 'icon', 'label' => 'Ikon (nama ikon Lucide, mis. speaker, lightbulb, mic, music, disc-3)', 'type' => 'text'],
            ['name' => 'card_style', 'label' => 'Gaya foto kartu', 'type' => 'select', 'options' => ['product' => 'Foto alat (latar abu-abu, utuh)', 'photo' => 'Foto penuh (talent / suasana)'], 'rules' => ['required']],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'integer']],
            ['name' => 'is_active', 'label' => 'Tampilkan di website', 'type' => 'checkbox'],
        ];
    }
}
