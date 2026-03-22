<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LobbyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function createLotWithSlots(string $name = 'Downtown Garage', int $slotCount = 10): array
    {
        $lot = ParkingLot::create([
            'name' => $name,
            'total_slots' => $slotCount,
            'available_slots' => $slotCount,
            'status' => 'open',
        ]);

        $slots = collect();
        for ($i = 1; $i <= $slotCount; $i++) {
            $slots->push(ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]));
        }

        return [$lot, $slots];
    }

    public function test_lobby_display_returns_lot_data(): void
    {
        [$lot] = $this->createLotWithSlots('Downtown Garage', 10);

        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lot_name', 'Downtown Garage')
            ->assertJsonPath('data.total_slots', 10)
            ->assertJsonPath('data.available_slots', 10)
            ->assertJsonPath('data.color_status', 'green')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'lot_id', 'lot_name', 'total_slots', 'available_slots',
                    'occupancy_percent', 'color_status', 'floors', 'timestamp',
                ],
                'error',
                'meta',
            ]);
    }

    public function test_lobby_display_is_public_no_auth_required(): void
    {
        [$lot] = $this->createLotWithSlots('Public Lot', 5);

        // No auth header — should still work
        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_lobby_display_returns_404_for_unknown_lot(): void
    {
        $response = $this->getJson('/api/v1/lots/'.Str::uuid().'/display');

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_lobby_display_calculates_occupancy_correctly(): void
    {
        [$lot, $slots] = $this->createLotWithSlots('Occupancy Lot', 10);
        $user = User::factory()->create();

        // Book 7 of 10 slots (70% occupied)
        foreach ($slots->take(7) as $slot) {
            Booking::create([
                'id' => Str::uuid(),
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'user_id' => $user->id,
                'status' => 'active',
                'start_time' => now()->subHour(),
                'end_time' => now()->addHour(),
            ]);
        }

        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_slots', 10)
            ->assertJsonPath('data.available_slots', 3)
            ->assertJsonPath('data.color_status', 'yellow');

        $occupancy = $response->json('data.occupancy_percent');
        $this->assertEquals(70, $occupancy);
    }

    public function test_lobby_display_red_status_at_high_occupancy(): void
    {
        [$lot, $slots] = $this->createLotWithSlots('Full Lot', 10);
        $user = User::factory()->create();

        // Book 9 of 10 slots (90% occupied)
        foreach ($slots->take(9) as $slot) {
            Booking::create([
                'id' => Str::uuid(),
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'user_id' => $user->id,
                'status' => 'active',
                'start_time' => now()->subHour(),
                'end_time' => now()->addHour(),
            ]);
        }

        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200)
            ->assertJsonPath('data.color_status', 'red')
            ->assertJsonPath('data.available_slots', 1);
    }

    public function test_lobby_display_includes_floor_breakdown(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Multi-Floor Garage',
            'total_slots' => 10,
            'available_slots' => 10,
            'status' => 'open',
        ]);

        $zone1 = Zone::create(['lot_id' => $lot->id, 'name' => 'B1', 'color' => '#FF0000']);
        $zone2 = Zone::create(['lot_id' => $lot->id, 'name' => 'B2', 'color' => '#00FF00']);

        for ($i = 1; $i <= 5; $i++) {
            ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => 'B1-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
                'zone_id' => $zone1->id,
            ]);
        }
        for ($i = 1; $i <= 5; $i++) {
            ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => 'B2-'.str_pad($i, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
                'zone_id' => $zone2->id,
            ]);
        }

        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200);

        $floors = $response->json('data.floors');
        $this->assertCount(2, $floors);
        $this->assertEquals('B1', $floors[0]['floor_name']);
        $this->assertEquals('B2', $floors[1]['floor_name']);
        $this->assertEquals(5, $floors[0]['total_slots']);
        $this->assertEquals(5, $floors[1]['total_slots']);
    }

    public function test_lobby_display_empty_lot_returns_zero_occupancy(): void
    {
        $lot = ParkingLot::create([
            'name' => 'Empty Lot',
            'total_slots' => 0,
            'available_slots' => 0,
            'status' => 'open',
        ]);

        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_slots', 0)
            ->assertJsonPath('data.available_slots', 0)
            ->assertJsonPath('data.occupancy_percent', 0)
            ->assertJsonPath('data.color_status', 'green')
            ->assertJsonPath('data.floors', []);
    }

    public function test_lobby_display_ignores_expired_bookings(): void
    {
        [$lot, $slots] = $this->createLotWithSlots('Expired Lot', 5);
        $user = User::factory()->create();

        // Create expired bookings (ended 2 hours ago)
        foreach ($slots as $slot) {
            Booking::create([
                'id' => Str::uuid(),
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'user_id' => $user->id,
                'status' => 'active',
                'start_time' => now()->subHours(3),
                'end_time' => now()->subHours(2),
            ]);
        }

        $response = $this->getJson("/api/v1/lots/{$lot->id}/display");

        $response->assertStatus(200)
            ->assertJsonPath('data.total_slots', 5)
            ->assertJsonPath('data.available_slots', 5)
            ->assertJsonPath('data.occupancy_percent', 0);
    }
}
