<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Authorization for `Notification` model.
 *
 * Ownership model: notifications are addressed to a single recipient
 * via `user_id`. Only the recipient may read or mutate their own
 * notifications. Platform admins retain read access for support.
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $user->isPlatformAdmin() || $user->id === $notification->user_id;
    }

    public function update(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $user->id === $notification->user_id;
    }
}
