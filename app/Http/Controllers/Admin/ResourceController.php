<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD generik untuk panel admin. Turunan cukup mendefinisikan model, judul,
 * kolom tabel dan field form — form, validasi & upload gambar ditangani di sini.
 *
 * Tipe field: text, textarea, number, money, select, image, gallery, tags, checkbox, email, password
 */
abstract class ResourceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $route;

    protected string $title;

    protected string $singular;

    protected ?string $slugFrom = null;

    protected array $with = [];

    protected array $searchable = ['name'];

    abstract protected function fields(?Model $item = null): array;

    /** [label => attribute|closure] */
    abstract protected function columns(): array;

    protected function query()
    {
        return $this->model::query()->with($this->with);
    }

    protected function extraView(): ?string
    {
        return null;
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $items = $this->query()
            ->when($search, fn ($q) => $q->where(fn ($w) => collect($this->searchable)->each(fn ($c) => $w->orWhere($c, 'like', "%$search%"))))
            ->when($this->hasColumn('sort_order'), fn ($q) => $q->orderBy('sort_order'))
            ->latest('id')
            ->paginate(20)->withQueryString();

        return view('admin.resource.index', [
            'items' => $items, 'columns' => $this->columns(), 'route' => $this->route,
            'title' => $this->title, 'singular' => $this->singular, 'search' => $search,
        ]);
    }

    public function create()
    {
        return $this->form(new $this->model);
    }

    public function edit(int $id)
    {
        return $this->form($this->model::findOrFail($id));
    }

    protected function form(Model $item)
    {
        return view('admin.resource.form', [
            'item' => $item, 'fields' => $this->fields($item), 'route' => $this->route,
            'title' => $this->title, 'singular' => $this->singular, 'extra' => $item->exists ? $this->extraView() : null,
        ]);
    }

    public function store(Request $request)
    {
        $item = new $this->model;
        $this->save($request, $item);

        return redirect()->route("admin.{$this->route}.edit", $item->id)->with('success', $this->singular.' berhasil dibuat.');
    }

    public function update(Request $request, int $id)
    {
        $item = $this->model::findOrFail($id);
        $this->save($request, $item);

        return back()->with('success', $this->singular.' berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $this->model::findOrFail($id)->delete();

        return redirect()->route("admin.{$this->route}.index")->with('success', $this->singular.' dihapus.');
    }

    protected function save(Request $request, Model $item): void
    {
        $fields = $this->fields($item);
        $rules = [];
        foreach ($fields as $f) {
            $rule = $f['rules'] ?? ['nullable'];
            if ($f['type'] === 'image') {
                $rule = ['nullable', 'image', 'max:5120'];
                $rules[$f['name'].'_url'] = ['nullable', 'url'];
            }
            if ($f['type'] === 'gallery') {
                $rules[$f['name'].'_files.*'] = ['nullable', 'image', 'max:5120'];
                $rule = ['nullable', 'string'];
            }
            if ($f['type'] === 'password' && $item->exists) {
                $rule = array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $rule);
            }
            $rules[$f['name']] = $rule;
        }
        $request->validate($rules);

        foreach ($fields as $f) {
            $name = $f['name'];
            switch ($f['type']) {
                case 'checkbox':
                    $item->{$name} = $request->boolean($name);
                    break;
                case 'image':
                    if ($request->hasFile($name)) {
                        $item->{$name} = $request->file($name)->store($this->route, 'public');
                    } elseif ($request->filled($name.'_url')) {
                        $item->{$name} = $request->input($name.'_url');
                    } elseif ($request->boolean($name.'_remove')) {
                        $item->{$name} = null;
                    }
                    break;
                case 'gallery':
                    $list = collect(preg_split('/\r?\n/', (string) $request->input($name)))->map(fn ($l) => trim($l))->filter()->values();
                    foreach ((array) $request->file($name.'_files', []) as $file) {
                        $list->push($file->store($this->route, 'public'));
                    }
                    $item->{$name} = $list->all();
                    break;
                case 'tags':
                    $item->{$name} = collect(explode(',', (string) $request->input($name)))->map(fn ($t) => trim($t))->filter()->values()->all();
                    break;
                case 'password':
                    if ($request->filled($name)) {
                        $item->{$name} = $request->input($name);
                    }
                    break;
                case 'number':
                    $item->{$name} = $request->filled($name) ? (int) $request->input($name) : ($f['default'] ?? null);
                    break;
                case 'money':
                    $item->{$name} = (int) preg_replace('/\D/', '', (string) $request->input($name));
                    break;
                default:
                    $item->{$name} = $request->input($name);
            }
        }

        if ($this->slugFrom && $this->hasColumn('slug')) {
            $item->slug = $this->uniqueSlug($item);
        }

        $item->save();
    }

    protected function uniqueSlug(Model $item): string
    {
        $base = Str::slug($item->{$this->slugFrom}) ?: Str::random(6);
        $slug = $base;
        $i = 2;
        while ($this->model::where('slug', $slug)->where('id', '!=', $item->id ?? 0)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function hasColumn(string $column): bool
    {
        static $cache = [];
        $table = (new $this->model)->getTable();

        return $cache[$table.$column] ??= \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
    }
}
