<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SwapRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'requester_booking_id', 'target_booking_id',
        'requester_id', 'target_id',
        'status', 'message',
    ];

    public function requesterBooking()
    {
        return $this->belongsTo(Booking::class, 'requester_booking_id');
    }

    public function targetBooking()
    {
        return $this->belongsTo(Booking::class, 'target_booking_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}
