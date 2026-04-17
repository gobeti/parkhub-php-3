<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\WidgetPolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class WidgetPolicyTest extends TestCase
{
    private WidgetPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WidgetPolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;

        return $user;
    }

    public function test_admins_can_manage_widgets(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $user = $this->makeUser($role);
            $this->assertTrue($this->policy->viewAny($user));
            $this->assertTrue($this->policy->update($user));
        }
    }

    public function test_regular_user_cannot_manage_widgets(): void
    {
        $user = $this->makeUser('user');
        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->update($user));
    }
}
