<?php

namespace Tests\Feature;

use App\Models\MaintenanceWindow;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.maintenance' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function createLot(): ParkingLot
    {
        return ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
    }

    public function test_list_maintenance_windows(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        MaintenanceWindow::create([
            'lot_id' => $lot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'reason' => 'Elevator repair',
            'affected_slots' => ['type' => 'all'],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/maintenance');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Elevator repair', $response->json('data.0.reason'));
    }

    public function test_create_maintenance_window(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/maintenance', [
            'lot_id' => $lot->id,
            'start_time' => now()->addDays(2)->toISOString(),
            'end_time' => now()->addDays(2)->addHours(6)->toISOString(),
            'reason' => 'Painting',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.reason', 'Painting');
        $response->assertJsonPath('data.lot_name', 'Test Lot');
    }

    public function test_update_maintenance_window(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $window = MaintenanceWindow::create([
            'lot_id' => $lot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'reason' => 'Old reason',
            'affected_slots' => ['type' => 'all'],
        ]);

        $response = $this->actingAs($admin)->putJson("/api/v1/admin/maintenance/{$window->id}", [
            'reason' => 'Updated reason',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.reason', 'Updated reason');
    }

    public function test_delete_maintenance_window(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $window = MaintenanceWindow::create([
            'lot_id' => $lot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'reason' => 'To delete',
            'affected_slots' => ['type' => 'all'],
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/v1/admin/maintenance/{$window->id}");

        $response->assertOk();
        $this->assertNull(MaintenanceWindow::find($window->id));
    }

    public function test_active_maintenance_windows(): void
    {
        $this->enableModule();
        $user = User::factory()->create();
        $lot = $this->createLot();

        // Active window (now)
        MaintenanceWindow::create([
            'lot_id' => $lot->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHours(3),
            'reason' => 'Active now',
            'affected_slots' => ['type' => 'all'],
        ]);

        // Future window
        MaintenanceWindow::create([
            'lot_id' => $lot->id,
            'start_time' => now()->addDays(5),
            'end_time' => now()->addDays(5)->addHours(4),
            'reason' => 'Future',
            'affected_slots' => ['type' => 'all'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/maintenance/active');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Active now', $response->json('data.0.reason'));
    }

    public function test_create_requires_valid_dates(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/maintenance', [
            'lot_id' => $lot->id,
            'start_time' => now()->addDay()->toISOString(),
            'end_time' => now()->subDay()->toISOString(), // end before start
            'reason' => 'Invalid',
        ]);

        $response->assertUnprocessable();
    }

    public function test_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/maintenance')->assertForbidden();
    }

    public function test_module_disabled_returns_404(): void
    {
        config(['modules.maintenance' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/maintenance')->assertNotFound();
    }

    public function test_create_with_specific_slots(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $lot = $this->createLot();
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'B1', 'status' => 'available']);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/maintenance', [
            'lot_id' => $lot->id,
            'start_time' => now()->addDays(3)->toISOString(),
            'end_time' => now()->addDays(3)->addHours(4)->toISOString(),
            'reason' => 'Spot repair',
            'affected_slots' => [$slot->id],
        ]);

        $response->assertCreated();
        $this->assertEquals('specific', $response->json('data.affected_slots.type'));
    }
}
