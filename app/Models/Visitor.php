<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasUuids;

    protected $fillable = [
        'host_user_id',
        'name',
        'email',
        'vehicle_plate',
        'visit_date',
        'purpose',
        'status',
        'qr_code',
        'pass_url',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_user_id');
    }
}
