<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CreditTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'booking_id', 'amount', 'type', 'description', 'granted_by',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
