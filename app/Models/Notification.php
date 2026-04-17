<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property ?string $title
 * @property ?string $message
 * @property ?array<string, mixed> $data
 * @property bool $read
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Notification extends Model
{
    use HasUuids;

    protected $table = 'notifications_custom';

    protected $fillable = ['user_id', 'type', 'title', 'message', 'data', 'read'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::created(function (Notification $notification) {
            // Fire-and-forget push notification (non-blocking)
            try {
                PushNotificationService::sendToUser(
                    $notification->user_id,
                    $notification->title ?? 'ParkHub',
                    $notification->message ?? '',
                );
            } catch (\Throwable) {
                // Push failure should never break app flow
            }
        });
    }
}
