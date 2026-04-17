<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateSwapRequestRequest;
use App\Http\Requests\RespondSwapRequestRequest;
use App\Http\Requests\SwapBookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\SwapRequestResource;
use App\Models\Booking;
use App\Models\Notification;
use App\Models\ParkingSlot;
use App\Models\SwapRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Slot swap flows: direct swap, swap-request creation and response,
 * and listing the current user's swap requests.
 *
 * Split out of BookingController (T-1743). Method bodies are moved
 * verbatim — behavioural refactors happen in a follow-up pass.
 */
class BookingSwapController extends Controller
{
    public function swap(SwapBookingRequest $request)
    {
        $booking = Booking::where('user_id', $request->user()->id)->findOrFail($request->booking_id);

        $newSlot = ParkingSlot::findOrFail($request->target_slot_id);

        // Validate that the target slot belongs to the same lot as the booking
        if ($newSlot->lot_id !== $booking->lot_id) {
            return response()->json(['error' => 'CROSS_LOT_SWAP', 'message' => 'Target slot must belong to the same lot as the current booking'], 422);
        }

        return DB::transaction(function () use ($booking, $newSlot, $request) {
            $conflict = Booking::where('slot_id', $request->target_slot_id)
                ->whereIn('status', [Booking::STATUS_CONFIRMED, Booking::STATUS_ACTIVE])
                ->where('start_time', '<', $booking->end_time)
                ->where('end_time', '>', $booking->start_time)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                return response()->json(['error' => 'SLOT_UNAVAILABLE'], 409);
            }
            $booking->update([
                'slot_id' => $request->target_slot_id,
                'slot_number' => $newSlot->slot_number,
            ]);

            return BookingResource::make($booking->fresh());
        });
    }

    public function createSwapRequest(CreateSwapRequestRequest $request, string $id)
    {
        $booking = Booking::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->findOrFail($id);

        $target = Booking::where('status', 'active')->findOrFail($request->target_booking_id);

        if ($target->user_id === $request->user()->id) {
            return response()->json(['error' => 'SELF_SWAP', 'message' => 'Cannot swap with your own booking'], 422);
        }

        // Check for existing pending swap request
        $existing = SwapRequest::where('requester_booking_id', $booking->id)
            ->where('target_booking_id', $target->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json(['error' => 'DUPLICATE', 'message' => 'Swap request already pending'], 409);
        }

        $swap = SwapRequest::create([
            'requester_booking_id' => $booking->id,
            'target_booking_id' => $target->id,
            'requester_id' => $request->user()->id,
            'target_id' => $target->user_id,
            'status' => 'pending',
            'message' => $request->message,
        ]);

        // Notify the target user
        Notification::create([
            'user_id' => $target->user_id,
            'type' => 'swap_request',
            'title' => 'Tausch-Anfrage',
            'message' => $request->user()->name.' möchte Stellplatz '.$booking->slot_number.' gegen '.$target->slot_number.' tauschen.',
            'data' => ['swap_request_id' => $swap->id],
        ]);

        return SwapRequestResource::make($swap->load(['requesterBooking', 'targetBooking', 'requester']))->response()->setStatusCode(201);
    }

    public function respondSwapRequest(RespondSwapRequestRequest $request, string $id)
    {
        $swap = SwapRequest::where('target_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        if ($request->accept) {
            // Perform the actual slot swap
            $requesterBooking = Booking::findOrFail($swap->requester_booking_id);
            $targetBooking = Booking::findOrFail($swap->target_booking_id);

            DB::transaction(function () use ($requesterBooking, $targetBooking) {
                $tempSlot = $requesterBooking->slot_id;
                $tempSlotNumber = $requesterBooking->slot_number;

                $requesterBooking->update([
                    'slot_id' => $targetBooking->slot_id,
                    'slot_number' => $targetBooking->slot_number,
                ]);

                $targetBooking->update([
                    'slot_id' => $tempSlot,
                    'slot_number' => $tempSlotNumber,
                ]);
            });

            $swap->update(['status' => 'accepted']);

            // Notify requester
            Notification::create([
                'user_id' => $swap->requester_id,
                'type' => 'swap_accepted',
                'title' => 'Tausch angenommen',
                'message' => $request->user()->name.' hat Ihren Tausch angenommen. Neuer Stellplatz: '.$requesterBooking->fresh()->slot_number,
            ]);
        } else {
            $swap->update(['status' => 'declined']);

            Notification::create([
                'user_id' => $swap->requester_id,
                'type' => 'swap_declined',
                'title' => 'Tausch abgelehnt',
                'message' => $request->user()->name.' hat Ihren Tausch abgelehnt.',
            ]);
        }

        return SwapRequestResource::make($swap->fresh()->load(['requesterBooking', 'targetBooking']));
    }

    public function swapRequests(Request $request)
    {
        // The frontend expects a flat SwapRequest[] array — direction is
        // derived from the requester_id field vs the current user. Returning
        // the paginated {incoming, outgoing} envelope (as we used to) crashed
        // the Swap Requests page with "w.map is not a function".
        $userId = $request->user()->id;
        $limit = min((int) $request->get('limit', 100), 200);

        $swaps = SwapRequest::where(function ($q) use ($userId) {
            $q->where('target_id', $userId)->orWhere('requester_id', $userId);
        })
            ->with(['requesterBooking', 'targetBooking', 'requester', 'target'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return SwapRequestResource::collection($swaps)->response();
    }
}
