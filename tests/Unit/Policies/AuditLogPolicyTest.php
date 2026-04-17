<?php

namespace Tests\Unit\Policies;

use App\Models\AuditLog;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogPolicyTest extends TestCase
{
    private AuditLogPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AuditLogPolicy;
    }

    private function makeUser(string $role = 'user'): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;

        return $user;
    }

    public function test_admins_can_read_audit_log(): void
    {
        foreach (['admin', 'superadmin'] as $role) {
            $user = $this->makeUser($role);
            $this->assertTrue($this->policy->viewAny($user));
            $this->assertTrue($this->policy->view($user, new AuditLog));
        }
    }

    public function test_regular_user_cannot_read_audit_log(): void
    {
        $user = $this->makeUser('user');
        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->view($user, new AuditLog));
    }

    public function test_nobody_can_mutate_audit_log_via_api(): void
    {
        foreach (['admin', 'superadmin', 'user'] as $role) {
            $user = $this->makeUser($role);
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, new AuditLog));
            $this->assertFalse($this->policy->delete($user, new AuditLog));
        }
    }
}
