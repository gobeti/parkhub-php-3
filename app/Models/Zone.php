<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $lot_id
 * @property string $name
 * @property ?string $color
 * @property ?string $description
 * @property ?string $tier
 * @property ?string $pricing_multiplier
 * @property ?int $max_capacity
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ParkingLot $lot
 * @property-read Collection<int, ParkingSlot> $slots
 */
class Zone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['lot_id', 'name', 'color', 'description', 'tier', 'pricing_multiplier', 'max_capacity'];

    public function lot()
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function slots()
    {
        return $this->hasMany(ParkingSlot::class);
    }
}
