<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    /**
     * POST /api/v1/geofence/check-in — auto check-in based on GPS proximity.
     *
     * Uses the haversine formula to calculate distance between
     * user's coordinates and the lot's geofence center.
     */
    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $userLat = (float) $validated['latitude'];
        $userLng = (float) $validated['longitude'];

        // Find active bookings for this user
        $activeBookings = Booking::where('user_id', $request->user()->id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', now()->addMinutes(30))
            ->where('end_time', '>=', now())
            ->get();

        foreach ($activeBookings as $booking) {
            $lot = ParkingLot::find($booking->lot_id);
            if (! $lot || ! $lot->center_lat || ! $lot->center_lng) {
                continue;
            }

            $radiusM = $lot->geofence_radius_m ?? 100;
            $distance = $this->haversineDistance(
                $userLat, $userLng,
                (float) $lot->center_lat, (float) $lot->center_lng
            );

            if ($distance <= $radiusM) {
                // Mark as active (checked in)
                $booking->update(['status' => 'active']);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'checked_in' => true,
                        'booking_id' => $booking->id,
                        'lot_name' => $lot->name,
                        'message' => 'Successfully checked in via geofence',
                    ],
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'checked_in' => false,
                'booking_id' => null,
                'lot_name' => null,
                'message' => 'No active bookings found within geofence range',
            ],
        ]);
    }

    /**
     * GET /api/v1/lots/{id}/geofence — get geofence config for a lot.
     */
    public function show(string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'lot_id' => $lot->id,
                'center_lat' => (float) ($lot->center_lat ?? 0),
                'center_lng' => (float) ($lot->center_lng ?? 0),
                'radius_meters' => (int) ($lot->geofence_radius_m ?? 100),
                'enabled' => (bool) ($lot->center_lat && $lot->center_lng),
            ],
        ]);
    }

    /**
     * PUT /api/v1/admin/lots/{id}/geofence — admin set geofence config.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'center_lat' => 'required|numeric|between:-90,90',
            'center_lng' => 'required|numeric|between:-180,180',
            'radius_meters' => 'required|integer|min:10|max:10000',
            'enabled' => 'nullable|boolean',
        ]);

        $lot = ParkingLot::findOrFail($id);

        $lot->update([
            'center_lat' => $validated['center_lat'],
            'center_lng' => $validated['center_lng'],
            'geofence_radius_m' => $validated['radius_meters'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'lot_id' => $lot->id,
                'center_lat' => (float) $lot->center_lat,
                'center_lng' => (float) $lot->center_lng,
                'radius_meters' => (int) $lot->geofence_radius_m,
                'enabled' => true,
            ],
        ]);
    }

    /**
     * Haversine formula — calculate distance between two GPS coordinates in meters.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
