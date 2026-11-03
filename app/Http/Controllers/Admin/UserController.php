<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserController extends ResourceController
{
    protected string $model = User::class;

    protected string $route = 'users';

    protected string $title = 'Pengguna';

    protected string $singular = 'Pengguna';

    protected array $searchable = ['name', 'email', 'company'];

    protected function columns(): array
    {
        return [
            'Nama' => 'name',
            'Email' => 'email',
            'Perusahaan' => 'company',
            'Telepon' => 'phone',
            'Role' => 'role',
            'Booking' => fn ($u) => $u->bookings()->count(),
        ];
    }

    protected function fields(?Model $item = null): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama', 'type' => 'text', 'rules' => ['required', 'max:100']],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'rules' => ['required', 'email', 'unique:users,email,'.($item?->id ?? 'NULL')]],
            ['name' => 'phone', 'label' => 'Telepon', 'type' => 'text'],
            ['name' => 'company', 'label' => 'Perusahaan / Akun Korporat', 'type' => 'text'],
            ['name' => 'address', 'label' => 'Alamat', 'type' => 'text'],
            ['name' => 'role', 'label' => 'Role', 'type' => 'select', 'options' => ['customer' => 'Customer', 'admin' => 'Admin'], 'rules' => ['required', 'in:customer,admin']],
            ['name' => 'password', 'label' => $item?->exists ? 'Password baru (kosongkan jika tidak diubah)' : 'Password', 'type' => 'password', 'rules' => ['required', 'min:8']],
        ];
    }
}
