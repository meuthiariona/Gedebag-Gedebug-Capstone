<?php

namespace App\Http\Controllers\Admin;

use App\Models\Package;
use App\Models\PackageAddon;
use App\Models\PackageCategory;
use App\Models\PackageItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PackageController extends ResourceController
{
    protected string $model = Package::class;

    protected string $route = 'packages';

    protected string $title = 'Paket Event';

    protected string $singular = 'Paket';

    protected ?string $slugFrom = 'name';

    protected array $with = ['category'];

    protected array $searchable = ['name', 'summary'];

    protected function columns(): array
    {
        return [
            'Foto' => 'img:image_url',
            'Nama' => 'name',
            'Kategori' => fn ($p) => $p->category?->name,
            'Harga' => fn ($p) => rupiah($p->price).' / '.$p->unit,
            'Stok' => 'stock',
            'Unggulan' => fn ($p) => $p->is_featured ? 'Ya' : '-',
            'Aktif' => fn ($p) => $p->is_active ? 'Ya' : 'Tidak',
        ];
    }

    protected function extraView(): ?string
    {
        return 'package';
    }

    protected function fields(?Model $item = null): array
    {
        return [
            ['name' => 'package_category_id', 'label' => 'Kategori Paket', 'type' => 'select', 'options' => PackageCategory::orderBy('sort_order')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:package_categories,id']],
            ['name' => 'name', 'label' => 'Nama Paket', 'type' => 'text', 'rules' => ['required', 'max:150']],
            ['name' => 'badge', 'label' => 'Label pada foto (mis. CORPORATE & CONFERENCE)', 'type' => 'text'],
            ['name' => 'summary', 'label' => 'Ringkasan isi paket (tampil di kartu)', 'type' => 'text', 'rules' => ['nullable', 'max:255']],
            ['name' => 'description', 'label' => 'Deskripsi lengkap', 'type' => 'textarea'],
            ['name' => 'series_label', 'label' => 'Label seri (mis. TIER 2 EXECUTIVE SERIES)', 'type' => 'text'],
            ['name' => 'tier', 'label' => 'Tier (mis. Tier 2)', 'type' => 'text'],
            ['name' => 'tags', 'label' => 'Tag foto, pisahkan dengan koma (mis. BEST SELLER, TURNKEY SOLUTION)', 'type' => 'tags'],
            ['name' => 'capacity_min', 'label' => 'Kapasitas minimal (pax)', 'type' => 'number', 'rules' => ['nullable', 'integer']],
            ['name' => 'capacity_max', 'label' => 'Kapasitas maksimal (pax)', 'type' => 'number', 'rules' => ['nullable', 'integer']],
            ['name' => 'power_watts', 'label' => 'Total daya sound (Watt)', 'type' => 'number', 'rules' => ['nullable', 'integer']],
            ['name' => 'price', 'label' => 'Harga (Rp)', 'type' => 'money', 'rules' => ['required']],
            ['name' => 'unit', 'label' => 'Satuan harga', 'type' => 'select', 'options' => ['event' => '/event', 'sesi' => '/sesi', 'hari' => '/hari'], 'rules' => ['required']],
            ['name' => 'stock', 'label' => 'Jumlah paket yang bisa jalan bersamaan (stok)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:0']],
            ['name' => 'engineers', 'label' => 'Jumlah engineer termasuk', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'integer', 'min:0']],
            ['name' => 'image', 'label' => 'Foto utama', 'type' => 'image'],
            ['name' => 'gallery', 'label' => 'Galeri foto', 'type' => 'gallery'],
            ['name' => 'sort_order', 'label' => 'Urutan', 'type' => 'number', 'default' => 0, 'rules' => ['nullable', 'integer']],
            ['name' => 'is_featured', 'label' => 'Tampilkan di beranda (unggulan)', 'type' => 'checkbox'],
            ['name' => 'is_active', 'label' => 'Tampilkan di website', 'type' => 'checkbox'],
        ];
    }

    public function storeItem(Request $request, Package $package)
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(PackageItem::TYPES))],
            'qty' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'max:150'],
            'subtitle' => ['nullable', 'max:200'],
            'tag' => ['nullable', 'max:60'],
        ]);
        $package->items()->create($data + ['sort_order' => $package->items()->count()]);

        return back()->with('success', 'Item paket ditambahkan.');
    }

    public function destroyItem(PackageItem $item)
    {
        $item->delete();

        return back()->with('success', 'Item paket dihapus.');
    }

    public function storeAddon(Request $request, Package $package)
    {
        $data = $request->validate([
            'name' => ['required', 'max:150'],
            'description' => ['nullable', 'max:200'],
            'price' => ['required'],
            'unit' => ['required', 'in:event,sesi,hari'],
        ]);
        $data['price'] = (int) preg_replace('/\D/', '', $data['price']);
        $package->addons()->create($data + ['sort_order' => $package->addons()->count()]);

        return back()->with('success', 'Add-on ditambahkan.');
    }

    public function destroyAddon(PackageAddon $addon)
    {
        $addon->delete();

        return back()->with('success', 'Add-on dihapus.');
    }
}
