<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createLotWithGeofence(float $lat = 48.1351, float $lng = 11.5820, int $radius = 100): ParkingLot
    {
        return ParkingLot::create([
            'name' => 'Geofenced Lot',
            'total_slots' => 20,
            'available_slots' => 10,
            'status' => 'open',
            'center_lat' => $lat,
            'center_lng' => $lng,
            'geofence_radius_m' => $radius,
        ]);
    }

    public function test_unauthenticated_cannot_check_in(): void
    {
        $response = $this->postJson('/api/v1/geofence/check-in', [
            'latitude' => 48.1351,
            'longitude' => 11.5820,
        ]);

        $response->assertStatus(401);
    }

    public function test_check_in_within_geofence(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithGeofence();
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'occupied']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'A1',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addHours(2),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/geofence/check-in', [
            'latitude' => 48.1351,
            'longitude' => 11.5820,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonPath('data.lot_name', 'Geofenced Lot');
    }

    public function test_check_in_outside_geofence(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithGeofence(48.1351, 11.5820, 50);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'B1', 'status' => 'occupied']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'B1',
            'start_time' => now()->subMinutes(5),
            'end_time' => now()->addHours(2),
            'status' => 'confirmed',
        ]);

        // Very far away coordinates
        $response = $this->actingAs($user)->postJson('/api/v1/geofence/check-in', [
            'latitude' => 52.5200,
            'longitude' => 13.4050,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.checked_in', false)
            ->assertJsonPath('data.booking_id', null);
    }

    public function test_check_in_no_active_bookings(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/geofence/check-in', [
            'latitude' => 48.1351,
            'longitude' => 11.5820,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.checked_in', false)
            ->assertJsonPath('data.message', 'No active bookings found within geofence range');
    }

    public function test_check_in_validation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/geofence/check-in', []);

        $response->assertStatus(422);
    }

    public function test_get_lot_geofence(): void
    {
        $user = User::factory()->create();
        $lot = $this->createLotWithGeofence(48.1351, 11.5820, 200);

        $response = $this->actingAs($user)->getJson("/api/v1/lots/{$lot->id}/geofence");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lot_id', $lot->id)
            ->assertJsonPath('data.radius_meters', 200)
            ->assertJsonPath('data.enabled', true);
    }

    public function test_get_lot_geofence_not_configured(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'No Fence',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->getJson("/api/v1/lots/{$lot->id}/geofence");

        $response->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_admin_set_geofence(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/lots/{$lot->id}/geofence", [
            'center_lat' => 48.0,
            'center_lng' => 11.0,
            'radius_meters' => 250,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.radius_meters', 250)
            ->assertJsonPath('data.enabled', true);

        $this->assertDatabaseHas('parking_lots', [
            'id' => $lot->id,
            'geofence_radius_m' => 250,
        ]);
    }

    public function test_non_admin_cannot_set_geofence(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/admin/lots/{$lot->id}/geofence", [
            'center_lat' => 48.0,
            'center_lng' => 11.0,
            'radius_meters' => 250,
        ]);

        $response->assertForbidden();
    }

    public function test_disabled_geofence_module_returns_404(): void
    {
        config(['modules.geofence' => false]);
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'X', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $this->actingAs($user)->postJson('/api/v1/geofence/check-in', ['latitude' => 48.0, 'longitude' => 11.0])->assertNotFound();
        $this->actingAs($user)->getJson("/api/v1/lots/{$lot->id}/geofence")->assertNotFound();
    }
}
