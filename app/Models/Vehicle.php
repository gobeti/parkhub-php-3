<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vehicle extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['user_id', 'plate', 'license_plate', 'make', 'model', 'color', 'vehicle_type', 'is_default', 'photo_url', 'flagged', 'flag_reason'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'flagged' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
