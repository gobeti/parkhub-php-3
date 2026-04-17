<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

/**
 * Authorization for `AuditLog` model.
 *
 * Audit log entries are admin-only. Platform admins see cross-tenant
 * entries; tenant admins see only entries scoped to their tenant via
 * the tenant global scope on downstream queries. No user may create,
 * update, or delete audit entries via the API — they're emitted by
 * the application itself.
 */
class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
