<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LotEdgeCaseExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_lot_with_duplicate_name_allowed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        ParkingLot::create(['name' => 'Garage A', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);

        // Creating a second lot with the same name should be allowed (no unique constraint on name)
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'Garage A', 'total_slots' => 3]);

        $response->assertStatus(201);
        $this->assertEquals(2, ParkingLot::where('name', 'Garage A')->count());
    }

    public function test_create_lot_missing_name_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['total_slots' => 5])
            ->assertStatus(422);
    }

    public function test_create_lot_zero_slots_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'Zero Lot', 'total_slots' => 0])
            ->assertStatus(422);
    }

    public function test_create_lot_exceeding_max_slots_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'Huge Lot', 'total_slots' => 1001])
            ->assertStatus(422);
    }

    public function test_create_lot_auto_generates_slots(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'Auto Lot', 'total_slots' => 5]);

        $response->assertStatus(201);
        $lotId = $response->json('data.id');
        $this->assertEquals(5, ParkingSlot::where('lot_id', $lotId)->count());
    }

    public function test_create_lot_default_slots_when_not_specified(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/lots', ['name' => 'Default Lot']);

        $response->assertStatus(201);
        $lotId = $response->json('data.id');
        $this->assertEquals(10, ParkingSlot::where('lot_id', $lotId)->count());
    }

    public function test_update_lot_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Original', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->putJson('/api/lots/'.$lot->id, ['name' => 'Renamed Lot'])
            ->assertStatus(200);

        $this->assertDatabaseHas('parking_lots', ['id' => $lot->id, 'name' => 'Renamed Lot']);
    }

    public function test_delete_lot_as_user_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'No Del', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/lots/'.$lot->id)
            ->assertStatus(403);
    }

    public function test_delete_lot_as_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Del OK', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/lots/'.$lot->id)
            ->assertStatus(200);

        $this->assertDatabaseMissing('parking_lots', ['id' => $lot->id]);
    }

    public function test_lot_show_with_layout_generation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Layout Lot', 'total_slots' => 3, 'available_slots' => 3, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'L1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'L2', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lots/'.$lot->id);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['layout' => ['rows']]]);
    }

    public function test_lot_occupancy_reflects_active_bookings(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'Occ Test', 'total_slots' => 3, 'available_slots' => 3, 'status' => 'open']);
        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'OT1', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'OT2', 'status' => 'available']);

        // One active booking
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot1->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/lots/'.$lot->id.'/occupancy');

        $response->assertStatus(200)
            ->assertJsonPath('data.occupied', 1)
            ->assertJsonPath('data.available', 1)
            ->assertJsonPath('data.total', 2);
    }

    public function test_lot_qr_code_nonexistent_lot(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/00000000-0000-0000-0000-000000000000/qr')
            ->assertStatus(404);
    }

    public function test_slot_qr_code(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $lot = ParkingLot::create(['name' => 'QR Slot Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'QS1', 'status' => 'available']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/lots/'.$lot->id.'/slots/'.$slot->id.'/qr');

        $response->assertStatus(200)
            ->assertJsonPath('data.slot_number', 'QS1');
    }
}
