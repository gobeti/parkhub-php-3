<?php

namespace Tests\Unit\Policies;

use App\Models\Tenant;
use App\Models\User;
use App\Policies\TenantPolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantPolicyTest extends TestCase
{
    private TenantPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new TenantPolicy;
    }

    private function makeUser(string $role = 'user', ?string $tenantId = null): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;
        $user->tenant_id = $tenantId;

        return $user;
    }

    private function makeTenant(?string $id = null): Tenant
    {
        $t = new Tenant;
        $t->id = $id ?? (string) Str::uuid();

        return $t;
    }

    public function test_platform_admin_can_create_tenant(): void
    {
        $this->assertTrue($this->policy->create($this->makeUser('superadmin')));
    }

    public function test_tenant_admin_cannot_create_tenant(): void
    {
        $admin = $this->makeUser('admin', (string) Str::uuid());

        $this->assertFalse($this->policy->create($admin));
    }

    public function test_regular_user_cannot_create_tenant(): void
    {
        $this->assertFalse($this->policy->create($this->makeUser('user')));
    }

    public function test_platform_admin_can_view_any_tenant(): void
    {
        $admin = $this->makeUser('superadmin');

        $this->assertTrue($this->policy->viewAny($admin));
        $this->assertTrue($this->policy->view($admin, $this->makeTenant()));
    }

    public function test_tenant_admin_can_view_own_tenant(): void
    {
        $tenantId = (string) Str::uuid();
        $admin = $this->makeUser('admin', $tenantId);
        $tenant = $this->makeTenant($tenantId);

        $this->assertTrue($this->policy->view($admin, $tenant));
    }

    public function test_tenant_admin_cannot_view_other_tenant(): void
    {
        $admin = $this->makeUser('admin', (string) Str::uuid());
        $otherTenant = $this->makeTenant((string) Str::uuid());

        $this->assertFalse($this->policy->view($admin, $otherTenant));
    }

    public function test_regular_user_cannot_view_tenant(): void
    {
        $this->assertFalse($this->policy->viewAny($this->makeUser('user')));
        $this->assertFalse($this->policy->view($this->makeUser('user'), $this->makeTenant()));
    }

    public function test_tenant_admin_can_update_own_tenant(): void
    {
        $tenantId = (string) Str::uuid();
        $admin = $this->makeUser('admin', $tenantId);
        $tenant = $this->makeTenant($tenantId);

        $this->assertTrue($this->policy->update($admin, $tenant));
    }

    public function test_tenant_admin_cannot_update_other_tenant(): void
    {
        $admin = $this->makeUser('admin', (string) Str::uuid());
        $otherTenant = $this->makeTenant((string) Str::uuid());

        $this->assertFalse($this->policy->update($admin, $otherTenant));
    }

    public function test_platform_admin_can_delete_tenant(): void
    {
        $this->assertTrue(
            $this->policy->delete($this->makeUser('superadmin'), $this->makeTenant())
        );
    }

    public function test_tenant_admin_cannot_delete_tenant(): void
    {
        $tenantId = (string) Str::uuid();
        $admin = $this->makeUser('admin', $tenantId);
        $tenant = $this->makeTenant($tenantId);

        $this->assertFalse($this->policy->delete($admin, $tenant));
    }
}
