<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Booking $booking) {}

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->booking->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'booking.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'lot_name' => $this->booking->lot_name,
            'slot_number' => $this->booking->slot_number,
            'status' => $this->booking->status,
        ];
    }
}
