<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Satu baris audit trail. Bersifat append-only: setelah tersimpan tidak bisa
 * diubah atau dihapus lewat aplikasi, supaya bukti tidak bisa dihilangkan.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public const ACTIONS = [
        'created' => 'Tambah',
        'updated' => 'Ubah',
        'deleted' => 'Hapus',
        'login' => 'Login',
        'login_failed' => 'Login gagal',
        'login_locked' => 'Login diblokir',
        'session_timeout' => 'Sesi habis',
        'backup_created' => 'Backup dibuat',
        'backup_downloaded' => 'Backup diunduh',
        'backup_uploaded' => 'Backup di-upload',
        'backup_restored' => 'Restore',
        'backup_deleted' => 'Backup dihapus',
        'calendar_synced' => 'Sinkron kalender',
        'logout' => 'Logout',
    ];

    /** Nama yang tampil di halaman log untuk tiap jenis data. */
    public const SUBJECTS = [
        'Booking' => 'Booking',
        'BookingItem' => 'Item booking',
        'Payment' => 'Pembayaran',
        'Setting' => 'Pengaturan',
        'User' => 'Pengguna',
        'Product' => 'Produk & talent',
        'Category' => 'Kategori produk',
        'Package' => 'Paket',
        'PackageCategory' => 'Kategori paket',
        'PackageItem' => 'Isi paket',
        'PackageAddon' => 'Add-on paket',
        'HeroSlide' => 'Banner',
    ];

    /** Pencatatan bisa dimatikan sementara, mis. saat seeding data contoh. */
    private static bool $enabled = true;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak boleh dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;
        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }

    /** Titik tunggal untuk menulis log, dipakai trait Auditable & event login. */
    public static function record(string $action, string $description, ?Model $subject = null, ?array $old = null, ?array $new = null, ?User $actor = null): ?self
    {
        if (! self::$enabled) {
            return null;
        }

        $actor ??= auth()->user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return static::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'Tamu / Sistem',
            'user_role' => $actor?->role,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_strimwidth($description, 0, 255, '…'),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }

    public function getActionLabelAttribute(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }

    public function getSubjectLabelAttribute(): ?string
    {
        return $this->subject_type ? (self::SUBJECTS[$this->subject_type] ?? $this->subject_type) : null;
    }

    /** Daftar perubahan per field: [field => [lama, baru]]. */
    public function getChangesListAttribute(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $out = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $field) {
            $out[$field] = [$old[$field] ?? null, $new[$field] ?? null];
        }

        return $out;
    }
}
