<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function setupLotAndSlot(): array
    {
        $lot = ParkingLot::create(['name' => 'Policy Lot', 'total_slots' => 5, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'P1', 'status' => 'available']);

        return [$lot, $slot];
    }

    public function test_booking_too_far_in_advance_rejected(): void
    {
        config(['parkhub.max_advance_days' => 7]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays(30)->toDateTimeString(),
                'end_time' => now()->addDays(30)->addHours(2)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'BOOKING_TOO_FAR_AHEAD');
    }

    public function test_max_active_bookings_enforced(): void
    {
        config(['parkhub.max_active_bookings' => 2]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Create 2 active bookings
        for ($i = 0; $i < 2; $i++) {
            $s = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'X'.$i, 'status' => 'available']);
            Booking::create([
                'user_id' => $user->id,
                'lot_id' => $lot->id,
                'slot_id' => $s->id,
                'lot_name' => $lot->name,
                'slot_number' => 'X'.$i,
                'start_time' => now()->addHours($i + 1),
                'end_time' => now()->addHours($i + 3),
                'status' => 'confirmed',
                'booking_type' => 'standard',
            ]);
        }

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHours(10)->toDateTimeString(),
                'end_time' => now()->addHours(12)->toDateTimeString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'MAX_ACTIVE_BOOKINGS');
    }

    public function test_admin_bypasses_booking_policies(): void
    {
        config(['parkhub.max_advance_days' => 7]);

        [$lot, $slot] = $this->setupLotAndSlot();
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addDays(30)->toDateTimeString(),
                'end_time' => now()->addDays(30)->addHours(2)->toDateTimeString(),
            ]);

        // Should not be rejected for advance days (admin bypass)
        $this->assertNotEquals(422, $response->status());
    }
}
