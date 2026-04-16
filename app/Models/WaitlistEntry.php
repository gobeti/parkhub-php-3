<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'priority',
        'status', 'notified_at', 'offer_expires_at', 'accepted_booking_id',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'offer_expires_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lot()
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function slot()
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }
}
