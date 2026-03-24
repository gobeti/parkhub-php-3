<?php

namespace Tests\Unit\Events;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_stores_booking(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $event = new BookingCreated($booking);
        $this->assertSame($booking, $event->booking);
    }

    public function test_broadcasts_on_private_user_channel(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $event = new BookingCreated($booking);
        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $booking = new Booking;
        $event = new BookingCreated($booking);
        $this->assertEquals('booking.created', $event->broadcastAs());
    }

    public function test_broadcast_with_contains_booking_data(): void
    {
        $user = User::factory()->create();
        $lot = ParkingLot::create(['name' => 'Test', 'total_slots' => 5]);
        $slot = ParkingSlot::create(['lot_id' => $lot->id, 'slot_number' => '001', 'status' => 'available']);
        $booking = Booking::create([
            'user_id' => $user->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(4),
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $event = new BookingCreated($booking);
        $data = $event->broadcastWith();
        $this->assertArrayHasKey('booking_id', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals($booking->id, $data['booking_id']);
    }
}
