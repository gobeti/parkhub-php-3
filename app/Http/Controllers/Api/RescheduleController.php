<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RescheduleController extends Controller
{
    /**
     * PUT /api/v1/bookings/{id}/reschedule — drag-to-reschedule with conflict check.
     */
    public function reschedule(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'new_start' => 'required|date',
            'new_end' => 'required|date|after:new_start',
        ]);

        $booking = Booking::where('user_id', $request->user()->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->findOrFail($id);

        $newStart = Carbon::parse($request->input('new_start'));
        $newEnd = Carbon::parse($request->input('new_end'));

        // Must be in the future
        if ($newStart->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'New start time must be in the future.',
            ], 422);
        }

        // Conflict check: any other booking on the same slot overlapping the new time
        $conflict = Booking::where('slot_id', $booking->slot_id)
            ->where('id', '!=', $booking->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<', $newEnd)
            ->where('end_time', '>', $newStart)
            ->exists();

        if ($conflict) {
            return response()->json([
                'success' => false,
                'message' => 'Conflict: the slot is already booked for the new time range.',
            ], 409);
        }

        $oldStart = $booking->start_time;

        $booking->update([
            'start_time' => $newStart,
            'end_time' => $newEnd,
        ]);

        // Notify user of reschedule
        Notification::create([
            'user_id' => $request->user()->id,
            'title' => 'Booking Rescheduled',
            'message' => 'Your booking was moved from '.$oldStart.' to '.$newStart->toDateTimeString().'.',
            'type' => 'booking_rescheduled',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Booking rescheduled successfully.',
            'booking' => $booking->fresh()->toArray(),
        ]);
    }
}
