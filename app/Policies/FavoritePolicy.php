<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Favorite;
use App\Models\User;

/**
 * Authorization for `Favorite` model.
 *
 * A Favorite is a user-scoped bookmark on a parking slot. Only the
 * owning user may read, create, or delete their favorites.
 */
class FavoritePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Favorite $favorite): bool
    {
        return $user->id === $favorite->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, Favorite $favorite): bool
    {
        return $user->id === $favorite->user_id;
    }
}
