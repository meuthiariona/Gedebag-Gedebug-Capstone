<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use Concerns\Auditable; // audit trail otomatis

    protected $guarded = ['id'];

    protected $casts = ['paid_at' => 'datetime'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
