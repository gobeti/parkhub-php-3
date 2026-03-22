<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_map_returns_lots_with_coordinates(): void
    {
        ParkingLot::create([
            'name' => 'Central Garage',
            'address' => '123 Main St',
            'latitude' => 48.1351,
            'longitude' => 11.5820,
            'total_slots' => 100,
            'available_slots' => 100,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/lots/map');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Central Garage')
            ->assertJsonPath('data.0.latitude', 48.1351)
            ->assertJsonPath('data.0.longitude', 11.582);
    }

    public function test_map_excludes_lots_without_coordinates(): void
    {
        ParkingLot::create([
            'name' => 'No Location',
            'total_slots' => 50,
            'available_slots' => 50,
            'status' => 'open',
        ]);

        ParkingLot::create([
            'name' => 'With Location',
            'latitude' => 48.0,
            'longitude' => 11.0,
            'total_slots' => 50,
            'available_slots' => 50,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/lots/map');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'With Location');
    }

    public function test_map_returns_correct_color_based_on_availability(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Color Test',
            'latitude' => 48.0,
            'longitude' => 11.0,
            'total_slots' => 100,
            'available_slots' => 100,
            'status' => 'open',
        ]);

        // No bookings — should be green (>50% available)
        $response = $this->getJson('/api/v1/lots/map');
        $response->assertJsonPath('data.0.color', 'green');
    }

    public function test_map_returns_gray_for_closed_lots(): void
    {
        ParkingLot::create([
            'name' => 'Closed Lot',
            'latitude' => 48.0,
            'longitude' => 11.0,
            'total_slots' => 100,
            'available_slots' => 100,
            'status' => 'closed',
        ]);

        $response = $this->getJson('/api/v1/lots/map');
        $response->assertJsonPath('data.0.color', 'gray');
    }

    public function test_admin_can_set_lot_location(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'No Location Yet',
            'total_slots' => 50,
            'available_slots' => 50,
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/lots/{$lot->id}/location", [
                'latitude' => 48.1351,
                'longitude' => 11.5820,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.latitude', 48.1351)
            ->assertJsonPath('data.longitude', 11.582);

        // Verify persisted
        $lot->refresh();
        $this->assertEquals(48.1351, (float) $lot->latitude);
    }

    public function test_non_admin_cannot_set_lot_location(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 50,
            'available_slots' => 50,
            'status' => 'open',
        ]);

        $this->actingAs($user)
            ->putJson("/api/v1/admin/lots/{$lot->id}/location", [
                'latitude' => 48.0,
                'longitude' => 11.0,
            ])
            ->assertStatus(403);
    }

    public function test_set_location_validates_coordinates(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Validation Test',
            'total_slots' => 50,
            'available_slots' => 50,
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/lots/{$lot->id}/location", [
                'latitude' => 999,
                'longitude' => 11.0,
            ])
            ->assertStatus(422);
    }

    public function test_map_disabled_when_module_off(): void
    {
        config(['modules.map' => false]);

        $this->getJson('/api/v1/lots/map')
            ->assertStatus(404);
    }

    public function test_map_returns_empty_when_no_lots(): void
    {
        $response = $this->getJson('/api/v1/lots/map');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
