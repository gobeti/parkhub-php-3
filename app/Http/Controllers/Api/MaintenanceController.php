<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MaintenanceWindow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    /**
     * GET /api/v1/admin/maintenance — list all maintenance windows.
     */
    public function index(): JsonResponse
    {
        $windows = MaintenanceWindow::with('lot')
            ->orderByDesc('start_time')
            ->get()
            ->map(fn (MaintenanceWindow $w) => $this->formatWindow($w));

        return response()->json(['success' => true, 'data' => $windows]);
    }

    /**
     * POST /api/v1/admin/maintenance — create a maintenance window.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lot_id' => 'required|uuid|exists:parking_lots,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'reason' => 'required|string|max:500',
            'affected_slots' => 'nullable|array',
        ]);

        // Check for booking overlaps
        $overlap = $this->hasBookingOverlap(
            $validated['lot_id'],
            $validated['start_time'],
            $validated['end_time'],
            $validated['affected_slots'] ?? null,
        );

        if ($overlap) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'BOOKING_OVERLAP', 'message' => 'Maintenance window overlaps with existing bookings'],
            ], 409);
        }

        $affectedSlots = null;
        if (! empty($validated['affected_slots'])) {
            $affectedSlots = ['type' => 'specific', 'slot_ids' => $validated['affected_slots']];
        } else {
            $affectedSlots = ['type' => 'all'];
        }

        $window = MaintenanceWindow::create([
            'lot_id' => $validated['lot_id'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'reason' => $validated['reason'],
            'affected_slots' => $affectedSlots,
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatWindow($window->load('lot')),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/maintenance/{id} — update a maintenance window.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $window = MaintenanceWindow::findOrFail($id);

        $validated = $request->validate([
            'lot_id' => 'sometimes|uuid|exists:parking_lots,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',
            'reason' => 'sometimes|string|max:500',
            'affected_slots' => 'nullable|array',
        ]);

        if (isset($validated['affected_slots'])) {
            if (! empty($validated['affected_slots'])) {
                $validated['affected_slots'] = ['type' => 'specific', 'slot_ids' => $validated['affected_slots']];
            } else {
                $validated['affected_slots'] = ['type' => 'all'];
            }
        }

        $window->update($validated);

        return response()->json([
            'success' => true,
            'data' => $this->formatWindow($window->load('lot')),
        ]);
    }

    /**
     * DELETE /api/v1/admin/maintenance/{id} — cancel a maintenance window.
     */
    public function destroy(string $id): JsonResponse
    {
        $window = MaintenanceWindow::findOrFail($id);
        $window->delete();

        return response()->json(['success' => true]);
    }

    /**
     * GET /api/v1/maintenance/active — public: list currently active maintenance windows.
     */
    public function active(): JsonResponse
    {
        $now = now();
        $windows = MaintenanceWindow::with('lot')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>', $now)
            ->get()
            ->map(fn (MaintenanceWindow $w) => $this->formatWindow($w));

        return response()->json(['success' => true, 'data' => $windows]);
    }

    private function formatWindow(MaintenanceWindow $w): array
    {
        return [
            'id' => $w->id,
            'lot_id' => $w->lot_id,
            'lot_name' => $w->lot?->name,
            'start_time' => $w->start_time->toISOString(),
            'end_time' => $w->end_time->toISOString(),
            'reason' => $w->reason,
            'affected_slots' => $w->affected_slots ?? ['type' => 'all'],
            'created_at' => $w->created_at?->toISOString(),
        ];
    }

    private function hasBookingOverlap(string $lotId, string $start, string $end, ?array $slotIds): bool
    {
        $query = Booking::where('status', '!=', 'cancelled')
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->whereHas('slot', function ($q) use ($lotId, $slotIds) {
                $q->where('lot_id', $lotId);
                if ($slotIds) {
                    $q->whereIn('id', $slotIds);
                }
            });

        return $query->exists();
    }
}
