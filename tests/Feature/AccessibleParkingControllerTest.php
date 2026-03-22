<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessibleParkingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.accessible' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createLotWithSlots(): array
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 4,
            'available_slots' => 4,
            'status' => 'open',
        ]);
        $s1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available', 'is_accessible' => true]);
        $s2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A2', 'status' => 'available', 'is_accessible' => true]);
        $s3 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A3', 'status' => 'available', 'is_accessible' => false]);
        $s4 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A4', 'status' => 'available', 'is_accessible' => false]);

        return ['lot' => $lot, 'slots' => [$s1, $s2, $s3, $s4]];
    }

    public function test_accessible_slots_returns_only_accessible(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $data = $this->createLotWithSlots();

        $response = $this->actingAs($admin)->getJson("/api/v1/lots/{$data['lot']->id}/slots/accessible");

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_toggle_accessible_on(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $data = $this->createLotWithSlots();
        $slot = $data['slots'][2]; // not accessible

        $response = $this->actingAs($admin)->putJson(
            "/api/v1/admin/lots/{$data['lot']->id}/slots/{$slot->id}/accessible",
            ['is_accessible' => true]
        );

        $response->assertOk();
        $response->assertJsonPath('data.is_accessible', true);
    }

    public function test_toggle_accessible_off(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $data = $this->createLotWithSlots();
        $slot = $data['slots'][0]; // accessible

        $response = $this->actingAs($admin)->putJson(
            "/api/v1/admin/lots/{$data['lot']->id}/slots/{$slot->id}/accessible",
            ['is_accessible' => false]
        );

        $response->assertOk();
        $response->assertJsonPath('data.is_accessible', false);
    }

    public function test_stats_returns_correct_data(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $this->createLotWithSlots();

        $response = $this->actingAs($admin)->getJson('/api/v1/bookings/accessible-stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_accessible_slots',
                'occupied_accessible_slots',
                'utilization_percent',
                'total_accessible_bookings',
                'users_with_accessibility_needs',
                'priority_booking_active',
                'priority_minutes',
            ],
        ]);
        $this->assertEquals(2, $response->json('data.total_accessible_slots'));
        $this->assertTrue($response->json('data.priority_booking_active'));
        $this->assertEquals(30, $response->json('data.priority_minutes'));
    }

    public function test_update_accessibility_needs(): void
    {
        $this->enableModule();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/users/me/accessibility-needs', [
            'accessibility_needs' => 'wheelchair',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.accessibility_needs', 'wheelchair');
        $this->assertEquals('wheelchair', $user->fresh()->accessibility_needs);
    }

    public function test_update_accessibility_needs_invalid(): void
    {
        $this->enableModule();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/users/me/accessibility-needs', [
            'accessibility_needs' => 'invalid_value',
        ]);

        $response->assertUnprocessable();
    }

    public function test_toggle_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);
        $data = $this->createLotWithSlots();
        $slot = $data['slots'][0];

        $response = $this->actingAs($user)->putJson(
            "/api/v1/admin/lots/{$data['lot']->id}/slots/{$slot->id}/accessible",
            ['is_accessible' => false]
        );

        $response->assertForbidden();
    }

    public function test_module_disabled_returns_404(): void
    {
        config(['modules.accessible' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/bookings/accessible-stats')->assertNotFound();
    }

    public function test_stats_counts_users_with_needs(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        User::factory()->create(['accessibility_needs' => 'wheelchair']);
        User::factory()->create(['accessibility_needs' => 'visual']);
        User::factory()->create(['accessibility_needs' => 'none']);

        $response = $this->actingAs($admin)->getJson('/api/v1/bookings/accessible-stats');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.users_with_accessibility_needs'));
    }
}
