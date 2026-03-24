<?php

namespace Tests\Unit\Events;

use App\Events\BookingCancelled;
use App\Models\Booking;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingCancelledTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_stores_booking(): void
    {
        $booking = new Booking;
        $event = new BookingCancelled($booking);
        $this->assertSame($booking, $event->booking);
    }

    public function test_broadcast_as_returns_correct_name(): void
    {
        $booking = new Booking;
        $event = new BookingCancelled($booking);
        $this->assertEquals('booking.cancelled', $event->broadcastAs());
    }

    public function test_broadcast_with_contains_booking_data(): void
    {
        $booking = new Booking;
        $booking->id = 'test-id';
        $booking->lot_name = 'Test Lot';
        $booking->slot_number = 'A1';
        $booking->status = 'cancelled';

        $event = new BookingCancelled($booking);
        $data = $event->broadcastWith();
        $this->assertArrayHasKey('booking_id', $data);
        $this->assertArrayHasKey('lot_name', $data);
        $this->assertArrayHasKey('slot_number', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('cancelled', $data['status']);
    }
}
