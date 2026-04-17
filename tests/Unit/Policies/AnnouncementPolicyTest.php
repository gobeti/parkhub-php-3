<?php

namespace Tests\Unit\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Policies\AnnouncementPolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnnouncementPolicyTest extends TestCase
{
    private AnnouncementPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AnnouncementPolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;

        return $user;
    }

    public function test_any_authenticated_user_can_view(): void
    {
        foreach (['user', 'admin', 'superadmin'] as $role) {
            $user = $this->makeUser($role);
            $this->assertTrue($this->policy->viewAny($user));
            $this->assertTrue($this->policy->view($user, new Announcement));
        }
    }

    public function test_admins_can_manage_announcements(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $user = $this->makeUser($role);
            $this->assertTrue($this->policy->create($user));
            $this->assertTrue($this->policy->update($user, new Announcement));
            $this->assertTrue($this->policy->delete($user, new Announcement));
        }
    }

    public function test_regular_user_cannot_manage_announcements(): void
    {
        $user = $this->makeUser('user');
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, new Announcement));
        $this->assertFalse($this->policy->delete($user, new Announcement));
    }
}
