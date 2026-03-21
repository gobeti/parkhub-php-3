<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingConflictTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private ParkingLot $lot;

    private ParkingSlot $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->lot = ParkingLot::create(['name' => 'Conflict Test Lot', 'total_slots' => 1]);
        $this->slot = ParkingSlot::create(['lot_id' => $this->lot->id, 'slot_number' => '001', 'status' => 'available']);
    }

    public function test_booking_same_slot_same_time_rejected(): void
    {
        // First booking succeeds
        $response = $this->actingAs($this->user)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(9)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(17)->format('Y-m-d H:i:s'),
        ]);
        $response->assertStatus(201);

        // Second booking on same slot overlapping time is rejected
        $response = $this->actingAs($this->otherUser)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(12)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(18)->format('Y-m-d H:i:s'),
        ]);
        $response->assertStatus(409);
    }

    public function test_booking_same_slot_non_overlapping_succeeds(): void
    {
        // Morning booking
        $this->actingAs($this->user)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(8)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(12)->format('Y-m-d H:i:s'),
        ])->assertStatus(201);

        // Afternoon booking (non-overlapping)
        $this->actingAs($this->otherUser)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(13)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(17)->format('Y-m-d H:i:s'),
        ])->assertStatus(201);
    }

    public function test_booking_past_start_time_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->subHour()->format('Y-m-d H:i:s'),
            'end_time' => now()->addHour()->format('Y-m-d H:i:s'),
        ]);
        $response->assertStatus(422);
    }

    public function test_cancelled_booking_does_not_block_slot(): void
    {
        // Create and cancel a booking
        $booking = Booking::create([
            'user_id' => $this->user->id,
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'status' => Booking::STATUS_CANCELLED,
        ]);

        // Same slot same time should work since previous was cancelled
        $response = $this->actingAs($this->otherUser)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(9)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(17)->format('Y-m-d H:i:s'),
        ]);
        $response->assertStatus(201);
    }

    public function test_auto_assign_slot_when_not_provided(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'start_time' => now()->addDay()->setHour(9)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(17)->format('Y-m-d H:i:s'),
        ]);
        $response->assertStatus(201);
    }

    public function test_no_slots_available_returns_409(): void
    {
        // Book the only slot
        Booking::create([
            'user_id' => $this->user->id,
            'lot_id' => $this->lot->id,
            'slot_id' => $this->slot->id,
            'start_time' => now()->addDay()->setHour(9),
            'end_time' => now()->addDay()->setHour(17),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        // Try to book without slot_id — no slots available
        $response = $this->actingAs($this->otherUser)->postJson('/api/bookings', [
            'lot_id' => $this->lot->id,
            'start_time' => now()->addDay()->setHour(10)->format('Y-m-d H:i:s'),
            'end_time' => now()->addDay()->setHour(16)->format('Y-m-d H:i:s'),
        ]);
        $response->assertStatus(409);
    }
}
