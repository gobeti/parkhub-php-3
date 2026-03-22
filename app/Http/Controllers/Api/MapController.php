<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapController extends Controller
{
    /**
     * GET /api/v1/lots/map
     *
     * Public endpoint — returns all lots that have latitude/longitude set,
     * enriched with availability data and a color indicator.
     */
    public function index(): JsonResponse
    {
        $now = now();

        $lots = ParkingLot::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        $markers = $lots->map(function (ParkingLot $lot) use ($now) {
            $totalSlots = $lot->total_slots;

            // Count currently occupied slots
            $occupiedCount = Booking::where('lot_id', $lot->id)
                ->whereIn('status', ['confirmed', 'active'])
                ->where('start_time', '<=', $now)
                ->where('end_time', '>=', $now)
                ->count();

            $availableSlots = max(0, $totalSlots - $occupiedCount);
            $occupancyPercent = $totalSlots > 0
                ? ($occupiedCount / $totalSlots) * 100
                : 0;

            // Color based on availability percentage
            $availablePercent = $totalSlots > 0
                ? ($availableSlots / $totalSlots) * 100
                : 0;

            $color = match (true) {
                $lot->status === 'closed' => 'gray',
                $availablePercent > 50 => 'green',
                $availablePercent >= 10 => 'yellow',
                default => 'red',
            };

            return [
                'id' => $lot->id,
                'name' => $lot->name,
                'address' => $lot->address ?? '',
                'latitude' => (float) $lot->latitude,
                'longitude' => (float) $lot->longitude,
                'available_slots' => $availableSlots,
                'total_slots' => $totalSlots,
                'status' => $lot->status,
                'color' => $color,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $markers->values(),
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * PUT /api/v1/admin/lots/{id}/location
     *
     * Admin-only: set/update latitude & longitude for a parking lot.
     */
    public function setLocation(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lot = ParkingLot::find($id);

        if (! $lot) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Parking lot not found'],
                'meta' => null,
            ], 404);
        }

        $lot->latitude = $request->latitude;
        $lot->longitude = $request->longitude;
        $lot->save();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $lot->id,
                'latitude' => (float) $lot->latitude,
                'longitude' => (float) $lot->longitude,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }
}
