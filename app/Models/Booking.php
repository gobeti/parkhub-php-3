<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_ACTIVE = 'active';

    const STATUS_CANCELLED = 'cancelled';

    const STATUS_COMPLETED = 'completed';

    const STATUS_NO_SHOW = 'no_show';

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'booking_type', 'lot_name', 'slot_number',
        'vehicle_plate', 'start_time', 'end_time', 'status', 'notes',
        'recurrence', 'checked_in_at',
        'base_price', 'tax_amount', 'total_price', 'currency',
        'tenant_id',
    ];

    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'checked_in_at' => 'datetime',
            'recurrence' => 'array',
            'base_price' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(ParkingLot::class, 'lot_id');
    }

    public function parkingLot(): BelongsTo
    {
        return $this->lot();
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }

    public function bookingNotes(): HasMany
    {
        return $this->hasMany(BookingNote::class);
    }
}
