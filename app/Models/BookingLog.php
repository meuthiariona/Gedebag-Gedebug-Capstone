<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['occurred_at' => 'datetime'];
}
