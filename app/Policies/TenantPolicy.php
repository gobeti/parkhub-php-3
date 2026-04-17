<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

/**
 * Authorization for `Tenant` model.
 *
 * Creating, updating, and deleting tenants is a platform-admin
 * operation (superadmin with no tenant_id). Viewing is allowed for
 * any admin: platform admins see every tenant, tenant admins see
 * only their own.
 */
class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->tenant_id === $tenant->id;
    }

    public function create(User $user): bool
    {
        return $user->isPlatformAdmin();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return $user->isAdmin() && $user->tenant_id === $tenant->id;
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->isPlatformAdmin();
    }
}
