<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParkingZoneControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.parking_zones' => true, 'modules.zones' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createLot(): ParkingLot
    {
        return ParkingLot::create([
            'name' => 'Test Lot',
            'address' => '123 Test St',
            'total_slots' => 100,
        ]);
    }

    public function test_list_zones_with_pricing(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        // Create a zone with pricing
        $this->actingAs($admin)->postJson("/api/v1/lots/{$lot->id}/zones/pricing", [
            'name' => 'Economy Section',
            'tier' => 'economy',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/v1/lots/{$lot->id}/zones/pricing");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Economy Section', $response->json('data.0.name'));
        $this->assertEquals('economy', $response->json('data.0.tier'));
        $this->assertEquals('Economy', $response->json('data.0.tier_display'));
        $this->assertEquals(0.8, $response->json('data.0.pricing_multiplier'));
    }

    public function test_create_zone_with_vip_tier(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $response = $this->actingAs($admin)->postJson("/api/v1/lots/{$lot->id}/zones/pricing", [
            'name' => 'VIP Lounge',
            'tier' => 'vip',
            'max_capacity' => 20,
        ]);

        $response->assertCreated();
        $this->assertEquals('vip', $response->json('data.tier'));
        $this->assertEquals(2.0, $response->json('data.pricing_multiplier'));
        $this->assertEquals(20, $response->json('data.max_capacity'));
    }

    public function test_create_zone_with_custom_multiplier(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $response = $this->actingAs($admin)->postJson("/api/v1/lots/{$lot->id}/zones/pricing", [
            'name' => 'Premium Plus',
            'tier' => 'premium',
            'pricing_multiplier' => 1.75,
        ]);

        $response->assertCreated();
        $this->assertEquals('premium', $response->json('data.tier'));
        $this->assertEquals(1.75, $response->json('data.pricing_multiplier'));
    }

    public function test_update_zone_pricing(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $create = $this->actingAs($admin)->postJson("/api/v1/lots/{$lot->id}/zones/pricing", [
            'name' => 'Flexible Zone',
            'tier' => 'standard',
        ]);

        $zoneId = $create->json('data.id');

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/zones/{$zoneId}/pricing", [
            'tier' => 'premium',
            'max_capacity' => 50,
        ]);

        $response->assertOk();
        $this->assertEquals('premium', $response->json('data.tier'));
        $this->assertEquals(1.5, $response->json('data.pricing_multiplier'));
        $this->assertEquals(50, $response->json('data.max_capacity'));
    }

    public function test_delete_zone_pricing_resets_to_standard(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $create = $this->actingAs($admin)->postJson("/api/v1/lots/{$lot->id}/zones/pricing", [
            'name' => 'Resettable Zone',
            'tier' => 'vip',
            'max_capacity' => 10,
        ]);

        $zoneId = $create->json('data.id');

        $response = $this->actingAs($admin)->deleteJson("/api/v1/lots/{$lot->id}/zones/{$zoneId}/pricing");
        $response->assertOk();

        // Verify it's reset
        $list = $this->actingAs($admin)->getJson("/api/v1/lots/{$lot->id}/zones/pricing");
        $zone = collect($list->json('data'))->firstWhere('id', $zoneId);
        $this->assertEquals('standard', $zone['tier']);
        $this->assertEquals(1.0, $zone['pricing_multiplier']);
    }

    public function test_invalid_tier_returns_validation_error(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $response = $this->actingAs($admin)->postJson("/api/v1/lots/{$lot->id}/zones/pricing", [
            'name' => 'Invalid Zone',
            'tier' => 'ultra_premium',
        ]);

        $response->assertStatus(422);
    }

    public function test_parking_zones_module_disabled_returns_404(): void
    {
        config(['modules.parking_zones' => false]);
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $this->actingAs($admin)->getJson("/api/v1/lots/{$lot->id}/zones/pricing")->assertNotFound();
    }
}
