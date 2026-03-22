<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Http\Controllers\Api\SseController;

/**
 * Pushes booking events to the SSE cache queue so connected
 * clients receive real-time updates.
 */
class PushSseBookingEvent
{
    public function handleCreated(BookingCreated $event): void
    {
        SseController::pushEvent($event->booking->user_id, 'booking_created', [
            'booking_id' => $event->booking->id,
            'lot_name' => $event->booking->lot_name,
            'slot_number' => $event->booking->slot_number,
            'start_time' => $event->booking->start_time,
            'end_time' => $event->booking->end_time,
        ]);
    }

    public function handleCancelled(BookingCancelled $event): void
    {
        SseController::pushEvent($event->booking->user_id, 'booking_cancelled', [
            'booking_id' => $event->booking->id,
            'lot_name' => $event->booking->lot_name,
            'slot_number' => $event->booking->slot_number,
        ]);
    }
}
