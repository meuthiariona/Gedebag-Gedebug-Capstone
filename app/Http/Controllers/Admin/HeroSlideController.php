<?php

namespace App\Http\Controllers\Admin;

use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Model;

class HeroSlideController extends ResourceController
{
    protected string $model = HeroSlide::class;

    protected string $route = 'hero-slides';

    protected string $title = 'Banner Beranda';

    protected string $singular = 'Banner';

    protected array $searchable = ['title_bold', 'title_light'];

    protected function columns(): array
    {
        return [
            'Gambar' => 'img:image_url',
            'Judul' => fn ($s) => $s->title_bold.' '.$s->title_light,
            'Urutan' => 'sort_order',
            'Aktif' => fn ($s) => $s->is_active ? 'Ya' : 'Tidak',
        ];
    }

    protected function fields(?Model $item = null): array
    {
        return [
            ['name' => 'eyebrow', 'label' => 'Teks kecil di atas judul (mis. SOUND • LIGHTING • TALENT)', 'type' => 'text'],
            ['name' => 'title_bold', 'label' => 'Judul (tebal)', 'type' => 'text', 'rules' => ['required', 'max:100']],
            ['name' => 'title_light', 'label' => 'Judul baris 2 (tipis)', 'type' => 'text'],
            ['name' => 'subtitle', 'label' => 'Deskripsi', 'type' => 'textarea'],
            ['name' => 'image', 'label' => 'Gambar latar', 'type' => 'image'],
            ['name' => 'button_text', 'label' => 'Teks tombol utama', 'type' => 'text'],
            ['name' => 'button_url', 'label' => 'Link tombol utama', 'type' => 'text'],
            ['name' => 'secondary_text', 'label' => 'Teks tombol kedua', 'type' => 'text'],
            ['name' => 'secondary_url', 'label' => 'Link tombol kedua (mis. URL video showreel)', 'type' => 'text'],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'integer']],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox'],
        ];
    }
}
