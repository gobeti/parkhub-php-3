<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class EvCharger extends Model
{
    use HasUuids;

    protected $fillable = [
        'lot_id',
        'label',
        'connector_type',
        'power_kw',
        'status',
        'location_hint',
    ];

    protected function casts(): array
    {
        return [
            'power_kw' => 'float',
        ];
    }

    public function lot()
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function sessions()
    {
        return $this->hasMany(ChargingSession::class, 'charger_id');
    }
}
