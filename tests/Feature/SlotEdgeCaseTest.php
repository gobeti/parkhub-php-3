<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndLot(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Slot Edge Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        return [$admin, $lot];
    }

    public function test_create_slot_with_type_and_features(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/slots', [
                'slot_number' => 'EV-1',
                'status' => 'available',
                'slot_type' => 'ev_charger',
                'features' => ['charging', 'wide'],
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parking_slots', [
            'lot_id' => $lot->id,
            'slot_number' => 'EV-1',
            'slot_type' => 'ev_charger',
        ]);
    }

    public function test_create_slot_with_zone(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $zone = Zone::create(['lot_id' => $lot->id, 'name' => 'Zone A']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/slots', [
                'slot_number' => 'ZN-1',
                'zone_id' => $zone->id,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parking_slots', [
            'lot_id' => $lot->id,
            'slot_number' => 'ZN-1',
            'zone_id' => $zone->id,
        ]);
    }

    public function test_create_slot_with_reserved_department(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/slots', [
                'slot_number' => 'RES-1',
                'reserved_for_department' => 'Management',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parking_slots', [
            'slot_number' => 'RES-1',
            'reserved_for_department' => 'Management',
        ]);
    }

    public function test_update_slot_number(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'OLD-1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/lots/'.$lot->id.'/slots/'.$slot->id, [
                'slot_number' => 'NEW-1',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'slot_number' => 'NEW-1']);
    }

    public function test_delete_slot_from_wrong_lot_returns_404(): void
    {
        [$admin, $lot1] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $lot2 = ParkingLot::create(['name' => 'Other', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot2->id, 'slot_number' => 'WL-1', 'status' => 'available']);

        // Try to delete slot via wrong lot
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/lots/'.$lot1->id.'/slots/'.$slot->id)
            ->assertStatus(404);

        // Slot should still exist
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id]);
    }

    public function test_update_slot_from_wrong_lot_returns_404(): void
    {
        [$admin, $lot1] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $lot2 = ParkingLot::create(['name' => 'Other2', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot2->id, 'slot_number' => 'WL-2', 'status' => 'available']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/lots/'.$lot1->id.'/slots/'.$slot->id, ['status' => 'maintenance'])
            ->assertStatus(404);
    }

    public function test_list_slots_with_type_filter(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Filter Lot', 'total_slots' => 3, 'available_slots' => 3, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S1', 'status' => 'available', 'slot_type' => 'standard']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'E1', 'status' => 'available', 'slot_type' => 'ev_charger']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S2', 'status' => 'available', 'slot_type' => 'standard']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/slots?type=ev_charger');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    public function test_admin_update_slot_via_admin_endpoint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Admin Slot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'AS1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/slots/'.$slot->id, [
                'status' => 'maintenance',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'maintenance']);
    }
}
