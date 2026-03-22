<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecommendationExtendedTest extends TestCase
{
    use RefreshDatabase;

    public function test_recommendations_include_reason_badges(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Badge Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 5.0,
        ]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('reason_badges', $data[0]);
        $this->assertContains('available_now', $data[0]['reason_badges']);
    }

    public function test_recommendations_weighted_scoring(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Weighted Lot',
            'total_slots' => 3,
            'available_slots' => 3,
            'status' => 'open',
            'hourly_rate' => 3.0,
        ]);

        $slot1 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        $slot2 = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '50', 'status' => 'available']);

        // Create history for slot1 to boost frequency score
        for ($i = 0; $i < 5; $i++) {
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $slot1->id,
                'lot_name' => 'Weighted Lot',
                'slot_number' => '1',
                'start_time' => now()->subDays($i + 1),
                'end_time' => now()->subDays($i + 1)->addHours(2),
                'status' => 'completed',
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // Slot 1 should score higher due to frequency (40% weight) + distance (10% weight)
        $this->assertEquals($slot1->id, $data[0]['slot_id']);
        $this->assertContains('your_usual_spot', $data[0]['reason_badges']);
    }

    public function test_recommendations_stats_endpoint(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->getJson('/api/v1/recommendations/stats');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.algorithm_weights.frequency', 40)
            ->assertJsonPath('data.algorithm_weights.availability', 30)
            ->assertJsonPath('data.algorithm_weights.price', 20)
            ->assertJsonPath('data.algorithm_weights.distance', 10);
    }

    public function test_recommendations_stats_with_bookings(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Stats Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Stats Lot',
            'slot_number' => '1',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'status' => 'completed',
        ]);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => 'Stats Lot',
            'slot_number' => '1',
            'start_time' => now(),
            'end_time' => now()->addHours(2),
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/recommendations/stats');

        $response->assertOk()
            ->assertJsonPath('data.total_recommendations', 2)
            ->assertJsonPath('data.accepted', 1)
            ->assertJsonPath('data.acceptance_rate', 50);
    }

    public function test_recommendations_price_scoring(): void
    {
        $user = User::factory()->create();

        $cheapLot = ParkingLot::create([
            'name' => 'Cheap Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 1.0,
        ]);

        $expensiveLot = ParkingLot::create([
            'name' => 'Expensive Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
            'hourly_rate' => 10.0,
        ]);

        ParkingSlot::create(['lot_id' => $cheapLot->id, 'slot_number' => '5', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $expensiveLot->id, 'slot_number' => '5', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // Cheap lot slot should rank higher due to price component (20% weight)
        $this->assertEquals($cheapLot->id, $data[0]['lot_id']);
    }

    public function test_recommendations_closest_entrance_badge(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Distance Lot',
            'total_slots' => 2,
            'available_slots' => 2,
            'status' => 'open',
        ]);

        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'available']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '100', 'status' => 'available']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk();
        $data = $response->json('data');
        // Slot 1 (closest) should have closest_entrance badge
        $slot1Data = collect($data)->firstWhere('slot_number', 1);
        $this->assertContains('closest_entrance', $slot1Data['reason_badges']);
    }

    public function test_recommendations_empty_when_no_available_slots(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create([
            'name' => 'Full Lot',
            'total_slots' => 2,
            'available_slots' => 0,
            'status' => 'open',
        ]);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '1', 'status' => 'occupied']);
        ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '2', 'status' => 'occupied']);

        $response = $this->actingAs($user)->getJson('/api/v1/bookings/recommendations');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_disabled_recommendations_module_returns_404(): void
    {
        config(['modules.recommendations' => false]);

        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/recommendations/stats')->assertNotFound();
    }
}
