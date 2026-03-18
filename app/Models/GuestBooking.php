<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuestBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'created_by', 'lot_id', 'slot_id', 'guest_name', 'guest_code',
        'start_time', 'end_time', 'vehicle_plate', 'status',
    ];

    protected function casts(): array
    {
        return ['start_time' => 'datetime', 'end_time' => 'datetime'];
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
