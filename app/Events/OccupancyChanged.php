<?php

namespace App\Events;

use App\Models\ParkingLot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when lot occupancy changes (booking created/cancelled).
 * The SSE controller also polls for changes, but this event allows
 * other listeners to react to occupancy updates.
 */
class OccupancyChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly ParkingLot $lot,
        public readonly int $available,
        public readonly int $total,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('occupancy.'.$this->lot->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'occupancy.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'lot_id' => $this->lot->id,
            'lot_name' => $this->lot->name,
            'available' => $this->available,
            'total' => $this->total,
        ];
    }
}
