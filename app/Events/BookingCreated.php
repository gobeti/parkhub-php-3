<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts on the authenticated user's private channel so that only
     * the booking owner receives the real-time update.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->booking->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'lot_name' => $this->booking->lot_name,
            'slot_number' => $this->booking->slot_number,
            'start_time' => $this->booking->start_time,
            'end_time' => $this->booking->end_time,
            'status' => $this->booking->status,
        ];
    }
}
