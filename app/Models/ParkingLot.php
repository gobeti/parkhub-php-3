<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ParkingLot extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'address', 'total_slots', 'available_slots', 'layout', 'status', 'hourly_rate', 'daily_max', 'monthly_pass', 'currency'];

    protected function casts(): array
    {
        return [
            'layout' => 'array',
            'total_slots' => 'integer',
            'available_slots' => 'integer',
            'hourly_rate' => 'decimal:2',
            'daily_max' => 'decimal:2',
            'monthly_pass' => 'decimal:2',
        ];
    }

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class, 'lot_id');
    }

    public function zones()
    {
        return $this->hasMany(Zone::class, 'lot_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'lot_id');
    }
}
