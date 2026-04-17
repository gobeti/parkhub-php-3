<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Webhook;
use App\Policies\WebhookPolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebhookPolicyTest extends TestCase
{
    private WebhookPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WebhookPolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;

        return $user;
    }

    public function test_admins_can_perform_all_actions(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $user = $this->makeUser($role);
            $this->assertTrue($this->policy->viewAny($user));
            $this->assertTrue($this->policy->view($user, new Webhook));
            $this->assertTrue($this->policy->create($user));
            $this->assertTrue($this->policy->update($user, new Webhook));
            $this->assertTrue($this->policy->delete($user, new Webhook));
        }
    }

    public function test_regular_users_cannot_perform_any_action(): void
    {
        $user = $this->makeUser('user');
        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, new Webhook));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user, new Webhook));
        $this->assertFalse($this->policy->delete($user, new Webhook));
    }
}
