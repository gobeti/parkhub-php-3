<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function authHeader(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('test')->plainTextToken];
    }

    private function createBooking(User $user, array $overrides = []): Booking
    {
        $lot = ParkingLot::create([
            'name' => 'Test Lot',
            'total_slots' => 10,
            'available_slots' => 5,
            'status' => 'open',
        ]);
        $slot = ParkingSlot::create([
            'lot_id' => $lot->id,
            'slot_number' => 'A1',
            'status' => 'available',
        ]);

        return Booking::create(array_merge([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'lot_name' => $lot->name,
            'slot_number' => 'A1',
            'start_time' => now()->addDays(2)->setHour(9)->setMinute(0),
            'end_time' => now()->addDays(2)->setHour(17)->setMinute(0),
            'status' => 'confirmed',
        ], $overrides));
    }

    public function test_user_can_reschedule_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user);

        $newStart = now()->addDays(5)->setHour(9)->setMinute(0)->toISOString();
        $newEnd = now()->addDays(5)->setHour(17)->setMinute(0)->toISOString();

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'new_start' => $newStart,
                'new_end' => $newEnd,
            ]);

        $response->assertStatus(200);
        $response->assertJsonFragment(['success' => true]);
    }

    public function test_reschedule_requires_future_start(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'new_start' => now()->subDay()->toISOString(),
                'new_end' => now()->toISOString(),
            ]);

        $response->assertStatus(422);
    }

    public function test_reschedule_detects_conflict(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user);

        // Create a conflicting booking on the same slot
        $newStart = now()->addDays(10)->setHour(9)->setMinute(0);
        $newEnd = now()->addDays(10)->setHour(17)->setMinute(0);

        Booking::create([
            'user_id' => $user->id,
            'lot_id' => $booking->lot_id,
            'slot_id' => $booking->slot_id,
            'lot_name' => 'Test Lot',
            'slot_number' => 'A1',
            'start_time' => $newStart,
            'end_time' => $newEnd,
            'status' => 'confirmed',
        ]);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'new_start' => $newStart->toISOString(),
                'new_end' => $newEnd->toISOString(),
            ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_cannot_reschedule_other_users_booking(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $booking = $this->createBooking($other);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'new_start' => now()->addDays(5)->toISOString(),
                'new_end' => now()->addDays(5)->addHours(8)->toISOString(),
            ]);

        $response->assertStatus(404);
    }

    public function test_cannot_reschedule_cancelled_booking(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user, ['status' => 'cancelled']);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'new_start' => now()->addDays(5)->toISOString(),
                'new_end' => now()->addDays(5)->addHours(8)->toISOString(),
            ]);

        $response->assertStatus(404);
    }

    public function test_reschedule_validates_end_after_start(): void
    {
        $user = User::factory()->create();
        $booking = $this->createBooking($user);

        $response = $this->withHeaders($this->authHeader($user))
            ->putJson("/api/v1/bookings/{$booking->id}/reschedule", [
                'new_start' => now()->addDays(5)->setHour(17)->toISOString(),
                'new_end' => now()->addDays(5)->setHour(9)->toISOString(),
            ]);

        $response->assertStatus(422);
    }
}
