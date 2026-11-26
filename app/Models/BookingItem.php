<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    use Concerns\Auditable; // audit trail otomatis

    protected $guarded = ['id'];

    protected $casts = ['inventory_components' => 'array'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? media_url($this->image) : null;
    }
}
