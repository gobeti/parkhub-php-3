<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WaitlistEntryResource;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\Setting;
use App\Models\WaitlistEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    /**
     * GET /api/v1/waitlist — legacy: list current user's waitlist entries.
     */
    public function index(Request $request): JsonResponse
    {
        $entries = WaitlistEntry::with('lot')
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->get();

        return response()->json(['success' => true, 'data' => WaitlistEntryResource::collection($entries)]);
    }

    /**
     * POST /api/v1/waitlist — legacy: join waitlist for a lot.
     */
    public function store(Request $request): JsonResponse
    {
        if (Setting::get('waitlist_enabled', 'true') !== 'true') {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'WAITLIST_DISABLED', 'message' => 'Waitlist is disabled.'],
            ], 403);
        }

        $request->validate([
            'lot_id' => 'required|uuid|exists:parking_lots,id',
        ]);

        $entry = WaitlistEntry::firstOrCreate([
            'user_id' => $request->user()->id,
            'lot_id' => $request->lot_id,
        ]);

        return response()->json(['success' => true, 'data' => WaitlistEntryResource::make($entry)], 201);
    }

    /**
     * DELETE /api/v1/waitlist/{id} — legacy: leave waitlist by entry id.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $entry = WaitlistEntry::where('user_id', $request->user()->id)->findOrFail($id);
        $entry->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    // ── Enhanced Waitlist (v3.7.0) ────────────────────────────────────

    /**
     * POST /api/v1/lots/{lotId}/waitlist/subscribe — join with priority.
     */
    public function subscribe(Request $request, string $lotId): JsonResponse
    {
        $lot = ParkingLot::findOrFail($lotId);

        $request->validate([
            'priority' => 'nullable|integer|min:1|max:5',
        ]);

        $existing = WaitlistEntry::where('user_id', $request->user()->id)
            ->where('lot_id', $lot->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'ALREADY_ON_WAITLIST', 'message' => 'You are already on the waitlist for this lot.'],
            ], 409);
        }

        $entry = WaitlistEntry::create([
            'user_id' => $request->user()->id,
            'lot_id' => $lot->id,
            'priority' => $request->input('priority', 3),
            'status' => 'waiting',
        ]);

        return response()->json(['success' => true, 'data' => WaitlistEntryResource::make($entry)], 201);
    }

    /**
     * GET /api/v1/lots/{lotId}/waitlist — view position + estimated wait.
     */
    public function lotWaitlist(Request $request, string $lotId): JsonResponse
    {
        $lot = ParkingLot::findOrFail($lotId);

        $entries = WaitlistEntry::where('lot_id', $lot->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        $positions = [];
        foreach ($entries->values() as $index => $entry) {
            $position = $index + 1;
            $estimatedMinutes = $entry->user_id === $request->user()->id
                ? max(0, $position - 1) * 30  // rough estimate: 30 min per position
                : null;

            if ($entry->user_id === $request->user()->id || $request->user()->role === 'admin') {
                $positions[] = [
                    'entry' => [
                        'id' => $entry->id,
                        'user_id' => $entry->user_id,
                        'lot_id' => $entry->lot_id,
                        'created_at' => $entry->created_at?->toISOString(),
                        'notified_at' => $entry->notified_at?->toISOString(),
                        'status' => $entry->status,
                        'offer_expires_at' => $entry->offer_expires_at?->toISOString(),
                        'accepted_booking_id' => $entry->accepted_booking_id,
                    ],
                    'position' => $position,
                    'total_ahead' => max(0, $position - 1),
                    'estimated_wait_minutes' => $estimatedMinutes,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $entries->count(),
                'entries' => $positions,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/lots/{lotId}/waitlist — leave waitlist for a lot.
     */
    public function leave(Request $request, string $lotId): JsonResponse
    {
        $lot = ParkingLot::findOrFail($lotId);

        $entry = WaitlistEntry::where('user_id', $request->user()->id)
            ->where('lot_id', $lot->id)
            ->whereIn('status', ['waiting', 'offered'])
            ->first();

        if (! $entry) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'NOT_ON_WAITLIST', 'message' => 'You are not on the waitlist for this lot.'],
            ], 404);
        }

        $entry->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    /**
     * POST /api/v1/lots/{lotId}/waitlist/{entryId}/accept — accept offered slot.
     */
    public function accept(Request $request, string $lotId, string $entryId): JsonResponse
    {
        $entry = WaitlistEntry::where('lot_id', $lotId)
            ->where('user_id', $request->user()->id)
            ->findOrFail($entryId);

        if ($entry->status !== 'offered') {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'NOT_OFFERED', 'message' => 'This entry has not been offered a slot.'],
            ], 422);
        }

        if ($entry->offer_expires_at && $entry->offer_expires_at->isPast()) {
            $entry->update(['status' => 'expired']);

            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'OFFER_EXPIRED', 'message' => 'The offer has expired.'],
            ], 410);
        }

        // Find an available slot and create a booking
        $lot = ParkingLot::findOrFail($lotId);
        $slot = ParkingSlot::where('lot_id', $lot->id)
            ->where('status', 'available')
            ->first();

        if (! $slot) {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'NO_SLOTS', 'message' => 'No available slots at this time.'],
            ], 409);
        }

        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'lot_id' => $lot->id,
            'slot_id' => $slot->id,
            'start_time' => now(),
            'end_time' => now()->addHours(8),
            'status' => 'confirmed',
        ]);

        $slot->update(['status' => 'occupied']);

        $entry->update([
            'status' => 'accepted',
            'accepted_booking_id' => $booking->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'entry' => WaitlistEntryResource::make($entry->fresh()),
                'booking_id' => $booking->id,
            ],
        ]);
    }

    /**
     * POST /api/v1/lots/{lotId}/waitlist/{entryId}/decline — decline, move to next.
     */
    public function decline(Request $request, string $lotId, string $entryId): JsonResponse
    {
        $entry = WaitlistEntry::where('lot_id', $lotId)
            ->where('user_id', $request->user()->id)
            ->findOrFail($entryId);

        if ($entry->status !== 'offered') {
            return response()->json([
                'success' => false, 'data' => null,
                'error' => ['code' => 'NOT_OFFERED', 'message' => 'This entry has not been offered a slot.'],
            ], 422);
        }

        $entry->update(['status' => 'declined']);

        // Offer to next person in queue
        $next = WaitlistEntry::where('lot_id', $lotId)
            ->where('status', 'waiting')
            ->orderBy('priority', 'asc')
            ->orderBy('created_at', 'asc')
            ->first();

        if ($next) {
            $next->update([
                'status' => 'offered',
                'notified_at' => now(),
                'offer_expires_at' => now()->addMinutes(15),
            ]);
        }

        return response()->json(['success' => true, 'data' => WaitlistEntryResource::make($entry->fresh())]);
    }
}
