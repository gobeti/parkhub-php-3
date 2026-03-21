<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingExtendTest extends TestCase
{
    use RefreshDatabase;

    private function setup(): array
    {
        $user = User::factory()->create(['role' => 'user']);
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 5,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'booking_type' => 'einmalig',
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHours(3),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        return [$user, $lot, $slot, $booking];
    }

    public function test_user_can_extend_booking(): void
    {
        [$user, $lot, $slot, $booking] = $this->setup();
        $token = $user->createToken('test')->plainTextToken;

        $newEndTime = now()->addHours(5)->toISOString();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/extend", [
                'new_end_time' => $newEndTime,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $booking->id);
    }

    public function test_extend_requires_new_end_time(): void
    {
        [$user, $lot, $slot, $booking] = $this->setup();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/extend", []);

        $response->assertStatus(422);
    }

    public function test_extend_fails_if_new_end_time_in_past(): void
    {
        [$user, $lot, $slot, $booking] = $this->setup();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/extend", [
                'new_end_time' => now()->subHour()->toISOString(),
            ]);

        $response->assertStatus(422);
    }

    public function test_extend_fails_on_slot_conflict(): void
    {
        [$user, $lot, $slot, $booking] = $this->setup();
        $user2 = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;

        // Another booking overlapping the extension window
        Booking::create([
            'user_id' => $user2->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'booking_type' => 'einmalig',
            'lot_name' => $lot->name,
            'slot_number' => $slot->slot_number,
            'start_time' => now()->addHours(4),
            'end_time' => now()->addHours(6),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/extend", [
                'new_end_time' => now()->addHours(5)->toISOString(),
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'SLOT_CONFLICT');
    }

    public function test_extend_requires_auth(): void
    {
        [$user, $lot, $slot, $booking] = $this->setup();

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/extend", [
            'new_end_time' => now()->addHours(5)->toISOString(),
        ]);

        $response->assertStatus(401);
    }

    public function test_user_cannot_extend_other_users_booking(): void
    {
        [$user, $lot, $slot, $booking] = $this->setup();
        $other = User::factory()->create(['role' => 'user']);
        $token = $other->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/v1/bookings/{$booking->id}/extend", [
                'new_end_time' => now()->addHours(5)->toISOString(),
            ]);

        $response->assertStatus(404);
    }
}
