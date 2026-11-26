<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFeedback extends Model
{
    protected $table = 'booking_feedback';

    protected $fillable = ['rating', 'comment'];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
