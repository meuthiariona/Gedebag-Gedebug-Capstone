<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;

/**
 * Pasang `use Auditable;` pada model, maka setiap tambah/ubah/hapus data model
 * itu otomatis tercatat di activity_logs lengkap dengan nilai sebelum & sesudah.
 */
trait Auditable
{
    /** Field yang tidak pernah dicatat nilainya. */
    protected static array $auditIgnored = ['updated_at', 'created_at', 'remember_token'];

    /** Field rahasia: tercatat bahwa berubah, tapi nilainya disamarkan. */
    protected static array $auditMasked = ['password'];

    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            ActivityLog::record('created', $model->auditLabel().' ditambahkan', $model, null, $model->auditValues($model->getAttributes()));
        });

        static::updated(function ($model) {
            $new = $model->auditValues($model->getChanges());
            if (! $new) {
                return; // hanya timestamp yang berubah
            }
            $old = $model->auditValues(array_intersect_key($model->getOriginal(), $new));
            ActivityLog::record('updated', $model->auditLabel().' diubah: '.implode(', ', array_keys($new)), $model, $old, $new);
        });

        static::deleted(function ($model) {
            ActivityLog::record('deleted', $model->auditLabel().' dihapus', $model, $model->auditValues($model->getAttributes()), null);
        });
    }

    protected function auditValues(array $values): array
    {
        $values = array_diff_key($values, array_flip(static::$auditIgnored));
        foreach (static::$auditMasked as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '•••••• (disamarkan)';
            }
        }

        return $values;
    }

    /** Teks singkat yang mengenali data ini di log, mis. "Pembayaran #12". */
    public function auditLabel(): string
    {
        $type = ActivityLog::SUBJECTS[class_basename($this)] ?? class_basename($this);
        $name = $this->code ?? $this->key ?? $this->name ?? $this->email ?? null;

        return trim($type.' #'.$this->getKey().($name ? " ({$name})" : ''));
    }
}
