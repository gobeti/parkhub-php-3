<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $lot_id
 * @property string $slot_id
 * @property array<int, string> $days_of_week
 * @property ?Carbon $start_date
 * @property ?Carbon $end_date
 * @property string $start_time
 * @property string $end_time
 * @property ?string $vehicle_plate
 * @property bool $active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RecurringBooking extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'lot_id', 'slot_id', 'days_of_week', 'start_date', 'end_date',
        'start_time', 'end_time', 'vehicle_plate', 'active',
    ];

    protected function casts(): array
    {
        return ['days_of_week' => 'array', 'active' => 'boolean'];
    }
}
