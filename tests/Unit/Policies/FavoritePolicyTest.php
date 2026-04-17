<?php

namespace Tests\Unit\Policies;

use App\Models\Favorite;
use App\Models\User;
use App\Policies\FavoritePolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class FavoritePolicyTest extends TestCase
{
    private FavoritePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new FavoritePolicy;
    }

    private function makeUser(): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = 'user';

        return $user;
    }

    private function makeFavorite(string $userId): Favorite
    {
        $f = new Favorite;
        $f->user_id = $userId;

        return $f;
    }

    public function test_any_user_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny($this->makeUser()));
    }

    public function test_any_user_can_create(): void
    {
        $this->assertTrue($this->policy->create($this->makeUser()));
    }

    public function test_owner_can_view_favorite(): void
    {
        $user = $this->makeUser();
        $fav = $this->makeFavorite($user->id);

        $this->assertTrue($this->policy->view($user, $fav));
    }

    public function test_non_owner_cannot_view_favorite(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $fav = $this->makeFavorite($owner->id);

        $this->assertFalse($this->policy->view($other, $fav));
    }

    public function test_owner_can_delete_favorite(): void
    {
        $user = $this->makeUser();
        $fav = $this->makeFavorite($user->id);

        $this->assertTrue($this->policy->delete($user, $fav));
    }

    public function test_non_owner_cannot_delete_favorite(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $fav = $this->makeFavorite($owner->id);

        $this->assertFalse($this->policy->delete($other, $fav));
    }
}
