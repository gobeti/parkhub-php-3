<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtendBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\AuditLog;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Check-in / extension actions for a booking.
 *
 * Split out of BookingController (T-1743) so the core booking CRUD
 * stays focused. Method bodies are intentionally moved verbatim —
 * any behavioural refactor happens in a follow-up pass.
 */
class BookingCheckInController extends Controller
{
    public function checkin(Request $request, string $id)
    {
        $booking = Booking::findOrFail($id);
        $this->authorize('update', $booking);
        $booking->update(['checked_in_at' => now(), 'status' => Booking::STATUS_ACTIVE]);
        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_checkin',
            'details' => ['booking_id' => $id],
        ]);

        return BookingResource::make($booking->fresh());
    }

    public function extend(ExtendBookingRequest $request, string $id): JsonResponse
    {
        $validated = $request->validated();

        $booking = Booking::where('user_id', $request->user()->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
            ->findOrFail($id);

        // Check no conflict with new end time
        $conflict = Booking::where('slot_id', $booking->slot_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
            ->where('start_time', '<', $validated['new_end_time'])
            ->where('end_time', '>', $booking->end_time)
            ->lockForUpdate()
            ->exists();

        if ($conflict) {
            return response()->json(['error' => 'SLOT_CONFLICT'], 409);
        }

        $oldEndTime = $booking->end_time;
        $booking->update(['end_time' => $validated['new_end_time']]);

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'booking_extended',
            'details' => [
                'booking_id' => $id,
                'old_end_time' => $oldEndTime,
                'new_end_time' => $validated['new_end_time'],
            ],
        ]);

        return response()->json(new BookingResource($booking->fresh()));
    }
}
