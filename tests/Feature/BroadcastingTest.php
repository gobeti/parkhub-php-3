<?php

namespace Tests\Feature;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $user): Booking
    {
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

        return Booking::create([
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
    }

    public function test_booking_created_event_is_dispatched_on_create(): void
    {
        Event::fake([BookingCreated::class]);

        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;
        $lot = ParkingLot::create(['name' => 'Lot', 'total_slots' => 5, 'available_slots' => 5, 'status' => 'open']);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => 'A1', 'status' => 'available']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/bookings', [
                'lot_id' => $lot->id,
                'slot_id' => $slot->id,
                'start_time' => now()->addHour()->toISOString(),
                'end_time' => now()->addHours(3)->toISOString(),
            ]);

        Event::assertDispatched(BookingCreated::class);
    }

    public function test_booking_cancelled_event_is_dispatched_on_cancel(): void
    {
        Event::fake([BookingCancelled::class]);

        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('test')->plainTextToken;
        $booking = $this->makeBooking($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/bookings/{$booking->id}");

        Event::assertDispatched(BookingCancelled::class);
    }

    public function test_booking_created_event_broadcasts_on_user_channel(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);
        $event = new BookingCreated($booking);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('user.'.$user->id, $channels[0]->name);
    }

    public function test_booking_cancelled_event_broadcasts_on_user_channel(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);
        $event = new BookingCancelled($booking);

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertStringContainsString('user.'.$user->id, $channels[0]->name);
    }

    public function test_booking_created_event_broadcast_name(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);
        $event = new BookingCreated($booking);

        $this->assertEquals('booking.created', $event->broadcastAs());
    }

    public function test_booking_cancelled_event_broadcast_name(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);
        $event = new BookingCancelled($booking);

        $this->assertEquals('booking.cancelled', $event->broadcastAs());
    }

    public function test_booking_created_broadcast_payload_contains_booking_id(): void
    {
        $user = User::factory()->create();
        $booking = $this->makeBooking($user);
        $event = new BookingCreated($booking);

        $payload = $event->broadcastWith();

        $this->assertArrayHasKey('booking_id', $payload);
        $this->assertEquals($booking->id, $payload['booking_id']);
    }

    public function test_channels_route_exists(): void
    {
        $this->assertFileExists(base_path('routes/channels.php'));
    }

    public function test_broadcasting_config_exists(): void
    {
        $this->assertFileExists(config_path('broadcasting.php'));
    }
}
