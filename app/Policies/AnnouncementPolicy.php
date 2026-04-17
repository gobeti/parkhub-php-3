<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;

/**
 * Authorization for `Announcement` model.
 *
 * Admins create/update/delete announcements; any authenticated user
 * may read them (the public `/announcements/active` endpoint is the
 * unauthenticated surface and is not gated by this policy).
 */
class AnnouncementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Announcement $announcement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Announcement $announcement): bool
    {
        return $user->isAdmin();
    }
}
