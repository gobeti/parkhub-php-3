<?php

namespace Tests\Feature;

use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_lot_requires_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'Lot', 'total_slots' => 5])
            ->assertStatus(403);
    }

    public function test_create_lot_as_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'New Lot', 'total_slots' => 3]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('parking_lots', ['name' => 'New Lot']);
    }

    public function test_update_lot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Old', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lots/'.$lot->id, ['name' => 'Updated'])
            ->assertStatus(200);

        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id, 'name' => 'Updated']);
    }

    public function test_delete_lot_requires_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Del', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/lots/'.$lot->id)
            ->assertStatus(403);
    }

    public function test_get_lot_not_found(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lots/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    public function test_lot_slots_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Slot Lot', 'total_slots' => 2, 'available_slots' => 2, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A2', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lots/'.$lot->id.'/slots');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_lot_occupancy_endpoint(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Occ Lot', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'O1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lots/'.$lot->id.'/occupancy');

        $response->assertStatus(200);
    }

    public function test_create_slot_as_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Slot Create', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots/'.$lot->id.'/slots', ['slot_number' => 'NEW1'])
            ->assertStatus(201);
    }

    public function test_create_slot_requires_admin(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'No Slot', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots/'.$lot->id.'/slots', ['slot_number' => 'X1'])
            ->assertStatus(403);
    }

    public function test_update_slot_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'USt', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'U1', 'status' => 'available']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lots/'.$lot->id.'/slots/'.$slot->id, ['status' => 'maintenance'])
            ->assertStatus(200);

        $this->assertDatabaseHas('parking_slots', ['id' => $slot->id, 'status' => 'maintenance']);
    }

    public function test_delete_slot(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Del Slot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'D1', 'status' => 'available']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/lots/'.$lot->id.'/slots/'.$slot->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('parking_slots', ['id' => $slot->id]);
    }

    public function test_list_lots(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        ParkingLot::create(['name' => 'Lot A', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);
        ParkingLot::create(['name' => 'Lot B', 'total_slots' => 3, 'available_slots' => 3, 'status' => 'open']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lots');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_lot_qr_code(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'QR Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/qr');

        $response->assertStatus(200);
    }
}
