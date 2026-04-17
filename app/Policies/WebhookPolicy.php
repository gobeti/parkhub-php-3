<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;

/**
 * Authorization for `Webhook` model.
 *
 * Webhooks are a tenant-wide integration surface — only admins may
 * manage them. The `admin` middleware already gates every webhook
 * route; this policy is defense-in-depth so a controller that
 * forgets the middleware still can't leak webhook config.
 */
class WebhookPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Webhook $webhook): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Webhook $webhook): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $user->isAdmin();
    }
}
