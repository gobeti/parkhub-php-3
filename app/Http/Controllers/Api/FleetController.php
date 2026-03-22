<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FleetController extends Controller
{
    /**
     * GET /api/v1/admin/fleet — list all vehicles across all users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::with('user');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('license_plate', 'like', "%{$search}%")
                    ->orWhere('make', 'like', "%{$search}%")
                    ->orWhere('model', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('vehicle_type', $type);
        }

        $vehicles = $query->orderByDesc('created_at')->get();

        $data = $vehicles->map(fn (Vehicle $v) => [
            'id' => $v->id,
            'user_id' => $v->user_id,
            'username' => $v->user?->username,
            'license_plate' => $v->license_plate ?? $v->plate ?? '',
            'make' => $v->make,
            'model' => $v->model,
            'color' => $v->color,
            'vehicle_type' => $v->vehicle_type ?? 'car',
            'is_default' => (bool) ($v->is_default ?? false),
            'created_at' => $v->created_at?->toISOString(),
            'bookings_count' => $v->bookings_count ?? 0,
            'last_used' => $v->last_used,
            'flagged' => (bool) ($v->flagged ?? false),
            'flag_reason' => $v->flag_reason,
        ]);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * GET /api/v1/admin/fleet/stats — fleet statistics.
     */
    public function stats(): JsonResponse
    {
        $vehicles = Vehicle::all();
        $total = $vehicles->count();

        $typesDist = $vehicles->groupBy(fn ($v) => $v->vehicle_type ?? 'car')
            ->map->count()
            ->toArray();

        $electricCount = $vehicles->filter(fn ($v) => ($v->vehicle_type ?? '') === 'electric')->count();
        $flaggedCount = $vehicles->filter(fn ($v) => (bool) ($v->flagged ?? false))->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_vehicles' => $total,
                'types_distribution' => $typesDist,
                'electric_count' => $electricCount,
                'electric_ratio' => $total > 0 ? round($electricCount / $total, 4) : 0,
                'flagged_count' => $flaggedCount,
            ],
        ]);
    }

    /**
     * PUT /api/v1/admin/fleet/{id}/flag — flag or unflag a vehicle.
     */
    public function flag(Request $request, string $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $validated = $request->validate([
            'flagged' => 'required|boolean',
            'reason' => 'nullable|string|max:500',
        ]);

        $vehicle->update([
            'flagged' => $validated['flagged'],
            'flag_reason' => $validated['flagged'] ? ($validated['reason'] ?? null) : null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $vehicle->id,
                'flagged' => (bool) $vehicle->flagged,
                'flag_reason' => $vehicle->flag_reason,
            ],
        ]);
    }
}
