<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

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
            'Peran' => fn ($u) => $u->roleLabel(),
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
            ['name' => 'role', 'label' => 'Peran (hak akses)', 'type' => 'select', 'options' => User::roleOptions(), 'rules' => ['required', 'in:'.implode(',', array_keys(config('rbac.roles')))]],
            ['name' => 'password', 'label' => $item?->exists ? 'Password baru (kosongkan jika tidak diubah)' : 'Password', 'type' => 'password', 'rules' => ['required', 'min:8']],
        ];
    }

    /** Cegah sistem terkunci: Owner terakhir tidak boleh diturunkan perannya. */
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isOwner() && $request->input('role') !== 'owner' && $this->ownerCount() <= 1) {
            return back()->withInput()->withErrors(['role' => 'Harus ada minimal satu Owner. Tambahkan Owner lain sebelum mengubah peran akun ini.']);
        }

        return parent::update($request, $id);
    }

    /** Cegah menghapus akun sendiri dan Owner terakhir. */
    public function destroy(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->is(auth()->user())) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun yang sedang dipakai.']);
        }
        if ($user->isOwner() && $this->ownerCount() <= 1) {
            return back()->withErrors(['user' => 'Owner terakhir tidak boleh dihapus.']);
        }

        return parent::destroy($id);
    }

    private function ownerCount(): int
    {
        return User::where('role', 'owner')->count();
    }
}
