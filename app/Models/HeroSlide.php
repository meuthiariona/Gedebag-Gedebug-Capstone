<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    use Concerns\Auditable; // audit trail otomatis

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function getImageUrlAttribute(): string
    {
        return media_url($this->image);
    }
}
