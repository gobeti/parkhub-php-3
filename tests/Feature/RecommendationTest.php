<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_cannot_get_recommendations(): void
    {
        $response = $this->getJson('/api/v1/bookings/recommendations');
        $response->assertStatus(401);
    }

    public function test_returns_available_slots(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Main Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '2', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '3', 'status' => 'occupied']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_max_5_recommendations(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Big Lot',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        for ($i = 1; $i <= 8; $i++) {
            ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => (string) $i, 'status' => 'available']);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_boosts_previously_used_slots(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Fav Lot',
            'total_slots' => 3,
            'available_slots' => 3,
            'status' => 'open',
        ]);

        $favSlot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '99', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        // Create 5 past bookings for favSlot — gives 5*4 = 20 point boost
        for ($i = 0; $i < 5; $i++) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $favSlot->id,
                'lot_name' => 'Fav Lot',
                'slot_number' => '99',
                'start_time' => now()->subDays($i + 1),
                'end_time' => now()->subDays($i + 1)->addHours(2),
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // The previously used slot should be ranked first
        $this->assertEquals($favSlot->id, $data[0]['slot_id']);
    }

    public function test_filters_by_lot_id(): void
    {
        $user = User::factory()->create();
        $lot1 = ParkingLot::create(['name' => 'Lot A', 'total_slots' => 2, 'available_slots' => 2, 'status' => 'open']);
        $lot2 = ParkingLot::create(['name' => 'Lot B', 'total_slots' => 2, 'available_slots' => 2, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot1->id, 'slot_number' => '1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot2->id, 'slot_number' => '1', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson("/api/v1/bookings/recommendations?lot_id={$lot1->id}");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($lot1->id, $data[0]['lot_id']);
    }

    public function test_recommendation_structure(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test Lot', 'total_slots' => 1, 'available_slots' => 1, 'status' => 'open']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['slot_id', 'slot_number', 'lot_id', 'lot_name', 'floor_name', 'score', 'reasons'],
                ],
            ]);
    }
}
