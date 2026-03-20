<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickBookTest extends TestCase
{
    use RefreshDatabase;

    private function createUserAndLot(int $totalSlots = 3): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Quick Lot',
            'total_slots' => $totalSlots,
            'available_slots' => $totalSlots,
            'status' => 'open',
        ]);

        $slots = [];
        for ($i = 1; $i <= $totalSlots; $i++) {
            $slots[] = ParkingSlot::create([
                'lot_id' => $lot->id,
                'slot_number' => 'Q'.$i,
                'status' => 'available',
            ]);
        }

        return [$user, $lot, $slots];
    }

    public function test_quick_book_assigns_best_available_slot(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', [
                'lot_id' => $lot->id,
                'date' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'lot_id' => $lot->id]);
    }

    public function test_quick_book_with_slot_id_directly(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', [
                'slot_id' => $slots[1]->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'slot_id' => $slots[1]->id,
        ]);
    }

    public function test_quick_book_when_no_lots_exist_returns_error(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        // Use a random UUID that doesn't map to any lot
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', [
                'lot_id' => fake()->uuid(),
                'date' => now()->addDay()->format('Y-m-d'),
            ]);

        // No slots exist for that lot → 409
        $response->assertStatus(409);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_quick_book_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/bookings/quick', [
            'lot_id' => fake()->uuid(),
            'date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertStatus(401);
    }

    public function test_quick_book_without_lot_or_slot_returns_422(): void
    {
        [$user] = $this->createUserAndLot();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings/quick', []);

        $response->assertStatus(422);
    }

    public function test_quick_book_skips_already_booked_slots(): void
    {
        [$user, $lot, $slots] = $this->createUserAndLot(2);
        $user2 = User::factory()->create(['role' => 'user']);
        $tomorrow = now()->addDay();

        // Book the first slot for tomorrow
        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slots[0]->id,
            'start_time' => $tomorrow->copy()->startOfDay(),
            'end_time' => $tomorrow->copy()->endOfDay(),
            'booking_type' => 'single',
            'status' => 'confirmed',
        ]);

        // Quick-book should assign second slot
        $token2 = $user2->createToken('test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token2)
            ->postJson('/api/v1/bookings/quick', [
                'lot_id' => $lot->id,
                'date' => $tomorrow->format('Y-m-d'),
            ]);

        $response->assertStatus(200);
        $booking = Booking::where('user_id', $user2->id)->first();
        $this->assertEquals($slots[1]->id, $booking->slot_id);
    }
