<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ChargingSession extends Model
{
    use HasUuids;

    protected $fillable = [
        'charger_id',
        'user_id',
        'start_time',
        'end_time',
        'kwh_consumed',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'kwh_consumed' => 'float',
        ];
    }

    public function charger()
    {
        return $this->belongsTo(EvCharger::class, 'charger_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
