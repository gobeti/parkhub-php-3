<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Vehicle;
use App\Policies\VehiclePolicy;
use Illuminate\Support\Str;
use Tests\TestCase;

class VehiclePolicyTest extends TestCase
{
    private VehiclePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new VehiclePolicy;
    }

    private function makeUser(string $role = 'user', ?string $tenantId = null): User
    {
        $user = new User;
        $user->id = (string) Str::uuid();
        $user->role = $role;
        $user->tenant_id = $tenantId;

        return $user;
    }

    private function makeVehicle(string $userId): Vehicle
    {
        $vehicle = new Vehicle;
        $vehicle->user_id = $userId;

        return $vehicle;
    }

    public function test_any_authenticated_user_can_view_any(): void
    {
        $user = $this->makeUser();

        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_owner_can_view_vehicle(): void
    {
        $user = $this->makeUser();
        $vehicle = $this->makeVehicle($user->id);

        $this->assertTrue($this->policy->view($user, $vehicle));
    }

    public function test_non_owner_cannot_view_vehicle(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $vehicle = $this->makeVehicle($owner->id);

        $this->assertFalse($this->policy->view($other, $vehicle));
    }

    public function test_platform_admin_can_view_any_vehicle(): void
    {
        $admin = $this->makeUser('superadmin');
        $owner = $this->makeUser();
        $vehicle = $this->makeVehicle($owner->id);

        $this->assertTrue($this->policy->view($admin, $vehicle));
    }

    public function test_tenant_admin_cannot_view_other_users_vehicle(): void
    {
        // An admin scoped to a tenant is NOT a platform admin; they don't
        // automatically get cross-user read access to vehicles.
        $admin = $this->makeUser('admin', (string) Str::uuid());
        $owner = $this->makeUser();
        $vehicle = $this->makeVehicle($owner->id);

        $this->assertFalse($this->policy->view($admin, $vehicle));
    }

    public function test_any_user_can_create_vehicle(): void
    {
        $user = $this->makeUser();

        $this->assertTrue($this->policy->create($user));
    }

    public function test_owner_can_update_vehicle(): void
    {
        $user = $this->makeUser();
        $vehicle = $this->makeVehicle($user->id);

        $this->assertTrue($this->policy->update($user, $vehicle));
    }

    public function test_non_owner_cannot_update_vehicle(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $vehicle = $this->makeVehicle($owner->id);

        $this->assertFalse($this->policy->update($other, $vehicle));
    }

    public function test_platform_admin_can_update_any_vehicle(): void
    {
        $admin = $this->makeUser('superadmin');
        $owner = $this->makeUser();
        $vehicle = $this->makeVehicle($owner->id);

        $this->assertTrue($this->policy->update($admin, $vehicle));
    }

    public function test_owner_can_delete_vehicle(): void
    {
        $user = $this->makeUser();
        $vehicle = $this->makeVehicle($user->id);

        $this->assertTrue($this->policy->delete($user, $vehicle));
    }

    public function test_non_owner_cannot_delete_vehicle(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $vehicle = $this->makeVehicle($owner->id);

        $this->assertFalse($this->policy->delete($other, $vehicle));
    }
}
