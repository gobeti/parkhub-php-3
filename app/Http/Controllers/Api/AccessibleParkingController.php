<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ToggleAccessibleSlotRequest;
use App\Http\Requests\UpdateAccessibilityNeedsRequest;
use App\Models\Booking;
use App\Models\ParkingSlot;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AccessibleParkingController extends Controller
{
    /**
     * GET /api/v1/lots/{id}/slots/accessible — list accessible slots for a lot.
     */
    public function accessibleSlots(string $id): JsonResponse
    {
        $slots = ParkingSlot::where('lot_id', $id)
            ->where('is_accessible', true)
            ->get()
            ->map(fn (ParkingSlot $s) => [
                'id' => $s->id,
                'lot_id' => $s->lot_id,
                'slot_number' => $s->slot_number,
                'status' => $s->status,
                'slot_type' => $s->slot_type,
                'is_accessible' => true,
            ]);

        return response()->json(['success' => true, 'data' => $slots]);
    }

    /**
     * PUT /api/v1/admin/lots/{id}/slots/{slot}/accessible — toggle accessible flag.
     */
    public function toggleAccessible(ToggleAccessibleSlotRequest $request, string $id, string $slot): JsonResponse
    {
        $parkingSlot = ParkingSlot::where('lot_id', $id)->where('id', $slot)->firstOrFail();

        $validated = $request->validated();

        $parkingSlot->update(['is_accessible' => $validated['is_accessible']]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $parkingSlot->id,
                'is_accessible' => (bool) $parkingSlot->is_accessible,
            ],
        ]);
    }

    /**
     * GET /api/v1/bookings/accessible-stats — accessible parking statistics.
     */
    public function stats(): JsonResponse
    {
        $totalAccessible = ParkingSlot::where('is_accessible', true)->count();
        $occupiedAccessible = ParkingSlot::where('is_accessible', true)
            ->where('status', 'occupied')
            ->count();

        $utilization = $totalAccessible > 0
            ? round(($occupiedAccessible / $totalAccessible) * 100, 1)
            : 0;

        $activeBookings = Booking::whereIn('slot_id', ParkingSlot::where('is_accessible', true)->pluck('id'))
            ->where('status', 'active')
            ->count();

        $usersWithNeeds = User::where('accessibility_needs', '!=', 'none')
            ->whereNotNull('accessibility_needs')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_accessible_slots' => $totalAccessible,
                'occupied_accessible_slots' => $occupiedAccessible,
                'utilization_percent' => $utilization,
                'total_accessible_bookings' => $activeBookings,
                'users_with_accessibility_needs' => $usersWithNeeds,
                'priority_booking_active' => true,
                'priority_minutes' => 30,
            ],
        ]);
    }

    /**
     * PUT /api/v1/users/me/accessibility-needs — update user's accessibility needs.
     */
    public function updateNeeds(UpdateAccessibilityNeedsRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->update(['accessibility_needs' => $validated['accessibility_needs']]);

        return response()->json([
            'success' => true,
            'data' => [
                'accessibility_needs' => $request->user()->accessibility_needs,
            ],
        ]);
    }
}
