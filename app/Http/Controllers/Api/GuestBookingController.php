<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestBookingRequest;
use App\Http\Resources\GuestBookingResource;
use App\Models\Booking;
use App\Models\GuestBooking;
use App\Models\ParkingSlot;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Guest booking flows: list / create / cancel guest passes created
 * by the current user.
 *
 * Split out of BookingController (T-1743). Method bodies are moved
 * verbatim — behavioural refactors happen in a follow-up pass.
 */
class GuestBookingController extends Controller
{
    /**
     * List guest passes created by the current user.
     *
     * The GuestPass page calls this on mount to render its booking table.
     * Without the endpoint the request 404'd and the list sat empty — the
     * page still "worked" (create flow was unaffected) but users couldn't
     * see or cancel their own guest passes.
     */
    public function listGuestBookings(Request $request): JsonResponse
    {
        $guests = GuestBooking::where('created_by', $request->user()->id)
            ->orderBy('start_time', 'desc')
            ->limit(200)
            ->get();

        return GuestBookingResource::collection($guests)->response();
    }

    /**
     * Cancel a guest pass owned by the current user.
     */
    public function deleteGuestBooking(Request $request, string $id): JsonResponse
    {
        $guest = GuestBooking::where('created_by', $request->user()->id)
            ->findOrFail($id);
        $guest->update(['status' => Booking::STATUS_CANCELLED]);

        return response()->json(['success' => true, 'data' => null, 'error' => null, 'meta' => null]);
    }

    public function guestBooking(StoreGuestBookingRequest $request)
    {
        // Enforce allow_guest_bookings setting
        if (Setting::get('allow_guest_bookings', 'false') !== 'true') {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'GUEST_BOOKINGS_DISABLED', 'message' => 'Guest bookings are disabled.'],
                'meta' => null,
            ], 403);
        }

        $slotId = $request->slot_id;
        if (! $slotId) {
            $startTime = $request->start_time ?? now();
            $bookedSlotIds = Booking::where('lot_id', $request->lot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $request->end_time)
                ->where('end_time', '>', $startTime)
                ->pluck('slot_id');
            $slot = ParkingSlot::where('lot_id', $request->lot_id)
                ->whereNotIn('id', $bookedSlotIds)
                ->first();
            if (! $slot) {
                return response()->json(['error' => 'NO_SLOTS_AVAILABLE'], 409);
            }
            $slotId = $slot->id;
        }

        $guest = null;

        try {
            DB::transaction(function () use ($request, $slotId, &$guest) {
                // Lock the slot row to prevent race conditions
                ParkingSlot::where('id', $slotId)->lockForUpdate()->firstOrFail();

                $guestStartTime = $request->start_time ?? now();

                // Re-check conflict with locked data
                $conflict = Booking::where('slot_id', $slotId)
                    ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                    ->where('start_time', '<', $request->end_time)
                    ->where('end_time', '>', $guestStartTime)
                    ->exists();

                if ($conflict) {
                    throw new \Exception('SLOT_CONFLICT');
                }

                $guest = GuestBooking::create([
                    'created_by' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'guest_name' => $request->guest_name,
                    'guest_code' => strtoupper(Str::random(8)),
                    'start_time' => $guestStartTime,
                    'end_time' => $request->end_time,
                    'vehicle_plate' => $request->vehicle_plate,
                    'status' => Booking::STATUS_CONFIRMED,
                ]);

                Booking::create([
                    'user_id' => $request->user()->id,
                    'lot_id' => $request->lot_id,
                    'slot_id' => $slotId,
                    'booking_type' => 'einmalig',
                    'vehicle_plate' => $request->vehicle_plate,
                    'start_time' => $guestStartTime,
                    'end_time' => $request->end_time,
                    'status' => Booking::STATUS_CONFIRMED,
                    'notes' => 'Guest: '.$request->guest_name,
                ]);
            }, 3);
        } catch (\Exception $e) {
            if ($e->getMessage() === 'SLOT_CONFLICT') {
                return response()->json(['error' => 'SLOT_UNAVAILABLE', 'message' => 'Slot is already booked'], 409);
            }
            throw $e;
        }

        return GuestBookingResource::make($guest)->response()->setStatusCode(201);
    }
}
