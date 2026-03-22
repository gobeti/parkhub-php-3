<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.multi_tenant' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_list_tenants_returns_empty_array(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/tenants');

        $response->assertOk();
        $response->assertJsonStructure(['success', 'data']);
        $this->assertTrue($response->json('success'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_create_tenant(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/tenants', [
            'name' => 'Acme Corp',
            'domain' => 'acme.example.com',
            'branding' => ['primary_color' => '#FF5733'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.name', 'Acme Corp');
        $response->assertJsonPath('data.domain', 'acme.example.com');
        $this->assertEquals('#FF5733', $response->json('data.branding.primary_color'));
    }

    public function test_create_tenant_validation(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/tenants', []);

        $response->assertStatus(422);
    }

    public function test_update_tenant(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $tenant = Tenant::create([
            'id' => fake()->uuid(),
            'name' => 'Old Name',
            'domain' => null,
        ]);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/tenants/{$tenant->id}", [
            'name' => 'New Name',
            'domain' => 'new.example.com',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'New Name');
        $response->assertJsonPath('data.domain', 'new.example.com');
    }

    public function test_list_tenants_includes_user_and_lot_counts(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $tenant = Tenant::create([
            'id' => fake()->uuid(),
            'name' => 'Counted Corp',
        ]);

        User::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->create(['tenant_id' => $tenant->id]);

        ParkingLot::create([
            'name' => 'Lot A',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
            'tenant_id' => $tenant->id,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/tenants');
        $response->assertOk();

        $tenantData = collect($response->json('data'))->firstWhere('name', 'Counted Corp');
        $this->assertEquals(2, $tenantData['user_count']);
        $this->assertEquals(1, $tenantData['lot_count']);
    }

    public function test_tenants_require_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/tenants')->assertForbidden();
    }

    public function test_tenants_require_auth(): void
    {
        $this->enableModule();

        $this->getJson('/api/v1/admin/tenants')->assertUnauthorized();
    }

    public function test_update_nonexistent_tenant_returns_404(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/tenants/nonexistent-id', ['name' => 'Test'])
            ->assertNotFound();
    }

    public function test_tenant_module_disabled_returns_404(): void
    {
        config(['modules.multi_tenant' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/tenants')->assertNotFound();
    }

    public function test_user_belongs_to_tenant(): void
    {
        $this->enableModule();

        $tenant = Tenant::create([
            'id' => fake()->uuid(),
            'name' => 'Test Tenant',
        ]);

        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->refresh();

        $this->assertEquals($tenant->id, $user->tenant_id);
        $this->assertInstanceOf(Tenant::class, $user->tenant);
    }
}
