<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageItem extends Model
{
    protected $guarded = ['id'];

    public const TYPES = ['hardware' => 'Included Hardware', 'talent' => 'Talent & Crew', 'service' => 'Services & Logistics'];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
