<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlotTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminAndLot(): array
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $lot = ParkingLot::create([
            'name' => 'Slot Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);

        return [$admin, $lot];
    }

    public function test_admin_can_list_slots_for_a_lot(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'S2', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/slots');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_a_slot(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/slots', [
                'slot_number' => 'NEW-1',
                'status' => 'available',
                'slot_type' => 'standard',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parking_slots', [
            'lot_id' => $lot->id,
            'slot_number' => 'NEW-1',
        ]);
    }

    public function test_admin_can_update_slot_status(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'U1',
            'status' => 'available',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/lots/'.$lot->id.'/slots/'.$slot->id, [
                'status' => 'maintenance',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('parking_slots', [
            'id' => $slot->id,
            'status' => 'maintenance',
        ]);
    }

    public function test_admin_can_delete_a_slot(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'D1',
            'status' => 'available',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/lots/'.$lot->id.'/slots/'.$slot->id);

        $response->assertStatus(200);
        $this->assertDatabaseMissing('parking_slots', ['id' => $slot->id]);
    }

    public function test_non_admin_cannot_create_slot(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Restricted Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/slots', [
                'slot_number' => 'NOPE',
            ]);

        $response->assertStatus(403);
    }

    public function test_non_admin_cannot_delete_slot(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Restricted Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'X1',
            'status' => 'available',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/lots/'.$lot->id.'/slots/'.$slot->id);

        $response->assertStatus(403);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id]);
    }

    public function test_non_admin_cannot_update_slot(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Restricted Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'X2',
            'status' => 'available',
        ]);
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/v1/lots/'.$lot->id.'/slots/'.$slot->id, [
                'status' => 'maintenance',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'available']);
    }

    public function test_create_slot_requires_slot_number(): void
    {
        [$admin, $lot] = $this->createAdminAndLot();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/lots/'.$lot->id.'/slots', [
                'status' => 'available',
            ]);

        $response->assertStatus(422);
    }
}
