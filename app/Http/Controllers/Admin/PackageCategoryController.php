<?php

namespace App\Http\Controllers\Admin;

use App\Models\PackageCategory;
use Illuminate\Database\Eloquent\Model;

class PackageCategoryController extends ResourceController
{
    protected string $model = PackageCategory::class;

    protected string $route = 'package-categories';

    protected string $title = 'Kategori Paket';

    protected string $singular = 'Kategori paket';

    protected ?string $slugFrom = 'name';

    protected function columns(): array
    {
        return [
            'Nama' => 'name',
            'Ikon' => 'icon',
            'Jumlah Paket' => fn ($c) => $c->packages()->count(),
            'Urutan' => 'sort_order',
        ];
    }

    protected function fields(?Model $item = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'max:100']],
            ['name' => 'icon', 'label' => 'Ikon (nama ikon Lucide)', 'type' => 'text'],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'integer']],
        ];
    }
}
