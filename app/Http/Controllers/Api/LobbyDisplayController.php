<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\Zone;
use App\Support\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LobbyDisplayController extends Controller
{
    /**
     * GET /api/v1/lots/{id}/display
     *
     * Public endpoint (no auth) — returns real-time occupancy data
     * formatted for full-screen lobby/kiosk monitors.
     */
    public function show(string $id): JsonResponse
    {
        $lot = ParkingLot::withCount('slots')->find($id);

        if (! $lot) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Parking lot not found'],
                'meta' => null,
            ], 404);
        }

        $now = now();
        $totalSlots = $lot->slots_count;

        // Count currently occupied slots for this lot
        $occupiedCount = Booking::where('lot_id', $lot->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->count();

        $availableSlots = max(0, $totalSlots - $occupiedCount);
        $occupancyPercent = $totalSlots > 0
            ? round(($occupiedCount / $totalSlots) * 100, 1)
            : 0;

        // Determine color status based on occupancy
        $colorStatus = match (true) {
            $occupancyPercent >= 90 => 'red',
            $occupancyPercent >= 60 => 'yellow',
            default => 'green',
        };

        // Build floor breakdown from zones
        $floors = $this->buildFloorBreakdown($lot->id, $now);

        return response()->json([
            'success' => true,
            'data' => [
                'lot_id' => $lot->id,
                'lot_name' => $lot->name,
                'total_slots' => $totalSlots,
                'available_slots' => $availableSlots,
                'occupancy_percent' => $occupancyPercent,
                'color_status' => $colorStatus,
                'floors' => $floors,
                'timestamp' => $now->toIso8601String(),
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * Build per-zone (floor) occupancy breakdown.
     */
    private function buildFloorBreakdown(string $lotId, \DateTimeInterface $now): array
    {
        $zones = Zone::where('lot_id', $lotId)
            ->withCount('slots')
            ->get();

        if ($zones->isEmpty()) {
            return [];
        }

        // Count occupied slots per zone in a single query. Raw
        // DB::table bypasses the Eloquent BelongsToTenant scope, so
        // apply the tenant filter explicitly when the flag is on.
        $bookingsQ = DB::table('bookings')
            ->join('parking_slots', 'bookings.slot_id', '=', 'parking_slots.id')
            ->where('bookings.lot_id', $lotId)
            ->whereIn('bookings.status', ['confirmed', 'active'])
            ->where('bookings.start_time', '<=', $now)
            ->where('bookings.end_time', '>=', $now)
            ->whereNotNull('parking_slots.zone_id');
        $occupiedByZone = TenantScope::applyTo($bookingsQ, 'bookings')
            ->select('parking_slots.zone_id', DB::raw('COUNT(*) as occupied'))
            ->groupBy('parking_slots.zone_id')
            ->pluck('occupied', 'zone_id');

        return $zones->map(function ($zone, $index) use ($occupiedByZone) {
            $total = $zone->slots_count;
            $occupied = $occupiedByZone->get($zone->id, 0);
            $available = max(0, $total - $occupied);
            $percent = $total > 0 ? round(($occupied / $total) * 100, 1) : 0;

            return [
                'floor_name' => $zone->name,
                'floor_number' => $index + 1,
                'total_slots' => $total,
                'available_slots' => $available,
                'occupancy_percent' => $percent,
            ];
        })->values()->all();
    }
}
