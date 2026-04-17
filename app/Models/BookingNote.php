<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $booking_id
 * @property string $user_id
 * @property string $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BookingNote extends Model
{
    use HasUuids;

    protected $fillable = ['booking_id', 'user_id', 'note'];
}
