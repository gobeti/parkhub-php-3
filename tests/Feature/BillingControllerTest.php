<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use RefreshDatabase;

    private function enableModule(): void
    {
        config(['modules.cost_center' => true]);
    }

    private function adminUser(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedBillingData(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        $user1 = User::factory()->create([
            'cost_center' => 'CC-100',
            'department' => 'Engineering',
        ]);
        $user2 = User::factory()->create([
            'cost_center' => 'CC-200',
            'department' => 'Marketing',
        ]);

        Booking::create([
            'user_id' => $user1->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
            'status' => 'completed',
            'total_price' => 10.50,
        ]);
        Booking::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->subHours(4),
            'end_time' => now()->subHours(3),
            'status' => 'completed',
            'total_price' => 5.00,
        ]);
    }

    public function test_by_cost_center(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $this->seedBillingData();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/billing/by-cost-center');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('CC-100', $data[0]['cost_center']);
    }

    public function test_by_department(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $this->seedBillingData();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/billing/by-department');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_export_csv(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $this->seedBillingData();

        $response = $this->actingAs($admin)->get('/api/v1/admin/billing/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_allocate_cost_center(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/billing/allocate', [
            'user_ids' => [$user1->id, $user2->id],
            'cost_center' => 'CC-300',
            'department' => 'Finance',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.updated', 2);
        $this->assertEquals('CC-300', $user1->fresh()->cost_center);
        $this->assertEquals('Finance', $user2->fresh()->department);
    }

    public function test_allocate_requires_valid_users(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/billing/allocate', [
            'user_ids' => ['non-existent-uuid'],
            'cost_center' => 'CC-400',
        ]);

        $response->assertUnprocessable();
    }

    public function test_requires_admin(): void
    {
        $this->enableModule();
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->getJson('/api/v1/admin/billing/by-cost-center')->assertForbidden();
    }

    public function test_module_disabled_returns_404(): void
    {
        config(['modules.cost_center' => false]);
        $admin = $this->adminUser();

        $this->actingAs($admin)->getJson('/api/v1/admin/billing/by-cost-center')->assertNotFound();
    }

    public function test_empty_billing_data(): void
    {
        $this->enableModule();
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->getJson('/api/v1/admin/billing/by-cost-center');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
    }
}
