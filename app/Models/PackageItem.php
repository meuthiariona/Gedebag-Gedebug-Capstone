<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageItem extends Model
{
    use Concerns\Auditable; // audit trail otomatis

    protected $guarded = ['id'];

    public const TYPES = ['hardware' => 'Included Hardware', 'talent' => 'Talent & Crew', 'service' => 'Services & Logistics'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
