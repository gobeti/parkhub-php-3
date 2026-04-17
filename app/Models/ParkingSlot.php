<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $lot_id
 * @property string $slot_number
 * @property string $status
 * @property ?string $slot_type
 * @property ?array<string, mixed> $features
 * @property ?string $reserved_for_department
 * @property ?string $zone_id
 * @property bool $is_accessible
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $number
 * @property-read ParkingLot $lot
 * @property-read ?Zone $zone
 * @property-read Collection<int, Booking> $bookings
 * @property-read ?Booking $activeBooking
 */
class ParkingSlot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['lot_id', 'slot_number', 'status', 'slot_type', 'features', 'reserved_for_department', 'zone_id', 'is_accessible'];

    protected $appends = ['number'];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'is_accessible' => 'boolean',
        ];
    }

    public function getNumberAttribute(): string
    {
        return $this->slot_number;
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'slot_id');
    }

    public function activeBooking(): HasOne
    {
        return $this->hasOne(Booking::class, 'slot_id')
            ->where('status', 'active')
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now());
    }
}
