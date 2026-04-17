<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $slot_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?ParkingSlot $slot
 */
class Favorite extends Model
{
    use HasUuids;

    protected $fillable = ['user_id', 'slot_id'];

    public function slot()
    {
        return $this->belongsTo(ParkingSlot::class, 'slot_id');
    }
}
