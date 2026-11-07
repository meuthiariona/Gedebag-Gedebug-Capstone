<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'phone', 'company', 'address'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Staf yang boleh masuk panel admin (owner, admin keuangan, admin operasional). */
    public function isAdmin(): bool
    {
        return $this->hasPermission('admin.access');
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /** Cek izin berdasarkan peran, sesuai matriks di config/rbac.php. */
    public function hasPermission(string $permission): bool
    {
        $granted = config("rbac.roles.{$this->role}.permissions", []);

        return in_array('*', $granted, true) || in_array($permission, $granted, true);
    }

    public function roleLabel(): string
    {
        return config("rbac.roles.{$this->role}.label", ucfirst((string) $this->role));
    }

    /** ['owner' => 'Owner', ...] untuk dropdown form. */
    public static function roleOptions(): array
    {
        return collect(config('rbac.roles'))->map(fn ($r) => $r['label'])->all();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }
}
