<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.fleet' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_fleet_returns_all_vehicles(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $user = User::factory()->create();
        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'AB-123',
            'make' => 'Tesla',
            'model' => 'Model 3',
            'vehicle_type' => 'electric',
        ]);
        Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'CD-456',
            'make' => 'BMW',
            'model' => '3er',
            'vehicle_type' => 'car',
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fleet');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_fleet_search_filters_vehicles(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $user = User::factory()->create();
        Vehicle::create(['user_id' => $user->id, 'plate' => 'AB-123', 'make' => 'Tesla', 'vehicle_type' => 'electric']);
        Vehicle::create(['user_id' => $user->id, 'plate' => 'XY-999', 'make' => 'BMW', 'vehicle_type' => 'car']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fleet?search=Tesla');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_fleet_stats_returns_statistics(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $user = User::factory()->create();
        Vehicle::create(['user_id' => $user->id, 'plate' => 'AB-123', 'vehicle_type' => 'electric']);
        Vehicle::create(['user_id' => $user->id, 'plate' => 'CD-456', 'vehicle_type' => 'car']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fleet/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => ['total_vehicles', 'types_distribution', 'electric_count', 'electric_ratio', 'flagged_count'],
        ]);
        $this->assertEquals(2, $response->json('data.total_vehicles'));
        $this->assertEquals(1, $response->json('data.electric_count'));
    }

    public function test_fleet_flag_vehicle(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $user = User::factory()->create();
        $vehicle = Vehicle::create(['user_id' => $user->id, 'plate' => 'AB-123', 'vehicle_type' => 'car']);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/fleet/{$vehicle->id}/flag", [
            'flagged' => true,
            'reason' => 'Suspicious activity',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.flagged', true);
        $response->assertJsonPath('data.flag_reason', 'Suspicious activity');
    }

    public function test_fleet_unflag_vehicle(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'user_id' => $user->id,
            'plate' => 'AB-123',
            'vehicle_type' => 'car',
            'flagged' => true,
            'flag_reason' => 'test',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/fleet/{$vehicle->id}/flag", [
            'flagged' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.flagged', false);
        $this->assertNull($response->json('data.flag_reason'));
    }

    public function test_fleet_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/fleet')->assertForbidden();
    }

    public function test_fleet_requires_auth(): void
    {
        $this->enableModule();

        $this->getJson('/api/v1/admin/fleet')->assertUnauthorized();
    }

    public function test_fleet_module_disabled_returns_404(): void
    {
        config(['modules.fleet' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/fleet')->assertNotFound();
    }

    public function test_fleet_filter_by_type(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $user = User::factory()->create();
        Vehicle::create(['user_id' => $user->id, 'plate' => 'AB-123', 'vehicle_type' => 'electric']);
        Vehicle::create(['user_id' => $user->id, 'plate' => 'CD-456', 'vehicle_type' => 'car']);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/fleet?type=electric');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
