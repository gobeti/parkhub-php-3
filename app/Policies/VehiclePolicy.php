<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Vehicle;

/**
 * Authorization for `Vehicle` model.
 *
 * Ownership model: each Vehicle belongs to a single User via `user_id`.
 * Only the owner may view / mutate their own vehicles via the user-facing
 * API. A platform admin (superadmin with no tenant_id) retains cross-tenant
 * visibility for support purposes.
 */
class VehiclePolicy
{
    /**
     * Any authenticated user may list vehicles; the controller further
     * scopes the query to `where user_id = current_user`.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->isPlatformAdmin() || $user->id === $vehicle->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->isPlatformAdmin() || $user->id === $vehicle->user_id;
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->isPlatformAdmin() || $user->id === $vehicle->user_id;
    }
}
