<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\EvCharger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EVChargingController extends Controller
{
    /**
     * GET /api/v1/lots/{id}/chargers — list chargers for a lot.
     */
    public function index(string $id): JsonResponse
    {
        $chargers = EvCharger::where('lot_id', $id)
            ->orderBy('label')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $chargers,
            'error' => null,
        ]);
    }

    /**
     * POST /api/v1/chargers/{id}/start — start a charging session.
     */
    public function start(string $id, Request $request): JsonResponse
    {
        $charger = EvCharger::findOrFail($id);

        if ($charger->status !== 'available') {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['message' => 'Charger is not available', 'code' => 'CHARGER_UNAVAILABLE'],
            ], 422);
        }

        // Check user doesn't already have an active session
        $existing = ChargingSession::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['message' => 'You already have an active charging session', 'code' => 'SESSION_ACTIVE'],
            ], 422);
        }

        $session = ChargingSession::create([
            'charger_id' => $charger->id,
            'user_id' => $request->user()->id,
            'start_time' => now(),
            'kwh_consumed' => 0,
            'status' => 'active',
        ]);

        $charger->update(['status' => 'in_use']);

        return response()->json([
            'success' => true,
            'data' => $session,
            'error' => null,
        ], 201);
    }

    /**
     * POST /api/v1/chargers/{id}/stop — stop a charging session.
     */
    public function stop(string $id, Request $request): JsonResponse
    {
        $charger = EvCharger::findOrFail($id);

        $session = ChargingSession::where('charger_id', $charger->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if (! $session) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['message' => 'No active session found', 'code' => 'NO_ACTIVE_SESSION'],
            ], 422);
        }

        $durationHours = now()->diffInMinutes($session->start_time) / 60;
        $kwh = round($charger->power_kw * $durationHours * 0.85, 2); // 85% efficiency

        $session->update([
            'end_time' => now(),
            'kwh_consumed' => $kwh,
            'status' => 'completed',
        ]);

        $charger->update(['status' => 'available']);

        return response()->json([
            'success' => true,
            'data' => $session->fresh(),
            'error' => null,
        ]);
    }

    /**
     * GET /api/v1/chargers/sessions — list user's charging sessions.
     */
    public function sessions(Request $request): JsonResponse
    {
        $sessions = ChargingSession::where('user_id', $request->user()->id)
            ->orderByDesc('start_time')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'error' => null,
        ]);
    }

    /**
     * GET /api/v1/admin/chargers — admin charger stats.
     */
    public function adminIndex(): JsonResponse
    {
        $chargers = EvCharger::all();
        $sessions = ChargingSession::all();

        $stats = [
            'total_chargers' => $chargers->count(),
            'available' => $chargers->where('status', 'available')->count(),
            'in_use' => $chargers->where('status', 'in_use')->count(),
            'offline' => $chargers->where('status', 'offline')->count(),
            'total_sessions' => $sessions->count(),
            'total_kwh' => round($sessions->sum('kwh_consumed'), 2),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'error' => null,
        ]);
    }

    /**
     * POST /api/v1/admin/chargers — create a new charger.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lot_id' => 'required|uuid|exists:parking_lots,id',
            'label' => 'required|string|max:100',
            'connector_type' => 'required|in:type2,ccs,chademo,tesla',
            'power_kw' => 'required|numeric|min:1|max:350',
            'location_hint' => 'nullable|string|max:255',
        ]);

        $charger = EvCharger::create([
            'lot_id' => $validated['lot_id'],
            'label' => $validated['label'],
            'connector_type' => $validated['connector_type'],
            'power_kw' => $validated['power_kw'],
            'status' => 'available',
            'location_hint' => $validated['location_hint'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => $charger,
            'error' => null,
        ], 201);
    }
}
