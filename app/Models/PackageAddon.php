<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageAddon extends Model
{
    use Concerns\Auditable; // audit trail otomatis

    protected $guarded = ['id'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
