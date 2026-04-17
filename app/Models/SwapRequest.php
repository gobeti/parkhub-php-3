<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $requester_booking_id
 * @property string $target_booking_id
 * @property string $requester_id
 * @property string $target_id
 * @property string $status
 * @property ?string $message
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Booking $requesterBooking
 * @property-read Booking $targetBooking
 * @property-read User $requester
 * @property-read User $target
 */
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
