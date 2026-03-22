<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperatingHoursController extends Controller
{
    private const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    private array $defaultDayHours = ['open' => '07:00', 'close' => '22:00', 'closed' => false];

    /**
     * GET /api/v1/lots/{id}/hours
     * Returns operating hours for a lot, plus is_open_now.
     */
    public function show(string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);
        $hours = $lot->operating_hours ?? [];
        $is24h = $hours['is_24h'] ?? true;

        $result = ['is_24h' => $is24h, 'is_open_now' => $lot->isOpenAt(now())];

        foreach (self::DAYS as $day) {
            $result[$day] = $hours[$day] ?? $this->defaultDayHours;
        }

        return response()->json($result);
    }

    /**
     * PUT /api/v1/admin/lots/{id}/hours
     * Set operating hours for each day.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $this->requireAdmin($request);

        $request->validate([
            'is_24h' => 'sometimes|boolean',
            'monday' => 'sometimes|array',
            'monday.open' => 'sometimes|date_format:H:i',
            'monday.close' => 'sometimes|date_format:H:i',
            'monday.closed' => 'sometimes|boolean',
            'tuesday' => 'sometimes|array',
            'tuesday.open' => 'sometimes|date_format:H:i',
            'tuesday.close' => 'sometimes|date_format:H:i',
            'tuesday.closed' => 'sometimes|boolean',
            'wednesday' => 'sometimes|array',
            'wednesday.open' => 'sometimes|date_format:H:i',
            'wednesday.close' => 'sometimes|date_format:H:i',
            'wednesday.closed' => 'sometimes|boolean',
            'thursday' => 'sometimes|array',
            'thursday.open' => 'sometimes|date_format:H:i',
            'thursday.close' => 'sometimes|date_format:H:i',
            'thursday.closed' => 'sometimes|boolean',
            'friday' => 'sometimes|array',
            'friday.open' => 'sometimes|date_format:H:i',
            'friday.close' => 'sometimes|date_format:H:i',
            'friday.closed' => 'sometimes|boolean',
            'saturday' => 'sometimes|array',
            'saturday.open' => 'sometimes|date_format:H:i',
            'saturday.close' => 'sometimes|date_format:H:i',
            'saturday.closed' => 'sometimes|boolean',
            'sunday' => 'sometimes|array',
            'sunday.open' => 'sometimes|date_format:H:i',
            'sunday.close' => 'sometimes|date_format:H:i',
            'sunday.closed' => 'sometimes|boolean',
        ]);

        $lot = ParkingLot::findOrFail($id);
        $current = $lot->operating_hours ?? [];

        $updated = ['is_24h' => $request->input('is_24h', $current['is_24h'] ?? true)];

        foreach (self::DAYS as $day) {
            if ($request->has($day)) {
                $updated[$day] = array_merge(
                    $current[$day] ?? $this->defaultDayHours,
                    $request->input($day)
                );
            } else {
                $updated[$day] = $current[$day] ?? $this->defaultDayHours;
            }
        }

        $lot->update(['operating_hours' => $updated]);

        $result = $updated;
        $result['is_open_now'] = $lot->isOpenAt(now());

        return response()->json($result);
    }

    private function requireAdmin(Request $request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }
}
