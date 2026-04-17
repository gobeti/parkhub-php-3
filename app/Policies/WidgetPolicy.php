<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Authorization for dashboard widgets.
 *
 * Widgets don't have a dedicated Eloquent model — layouts are stored as
 * JSON in the `settings` table keyed by user id, and widget data queries
 * are admin-only aggregates. This policy therefore operates on the `User`
 * model and is registered under the `widgets` Gate ability name; the
 * routes are already gated by the `admin` middleware, this policy adds a
 * defense-in-depth check for controllers that forget it.
 */
class WidgetPolicy
{
    /**
     * Only admins may view the widget layout + aggregated dashboard data.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Only admins may update the widget layout.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
