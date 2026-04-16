<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class VisitorController extends Controller
{
    /**
     * POST /api/v1/visitors/register — register a new visitor.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'vehicle_plate' => 'nullable|string|max:20',
            'visit_date' => 'required|date',
            'purpose' => 'nullable|string|max:500',
        ]);

        $visitor = Visitor::create([
            'host_user_id' => $request->user()->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'vehicle_plate' => $validated['vehicle_plate'] ?? null,
            'visit_date' => $validated['visit_date'],
            'purpose' => $validated['purpose'] ?? null,
            'status' => 'pending',
            'qr_code' => 'data:image/png;base64,'.base64_encode(Str::random(32)),
            'pass_url' => '/visitor-pass/'.Str::uuid(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $visitor,
            'error' => null,
        ], 201);
    }

    /**
     * GET /api/v1/visitors — list current user's visitors.
     */
    public function index(Request $request): JsonResponse
    {
        $visitors = Visitor::where('host_user_id', $request->user()->id)
            ->orderByDesc('visit_date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $visitors,
            'error' => null,
        ]);
    }

    /**
     * GET /api/v1/admin/visitors — list all visitors (admin only).
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Visitor::with('host');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('vehicle_plate', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $visitors = $query->orderByDesc('visit_date')->get();

        return response()->json([
            'success' => true,
            'data' => $visitors,
            'error' => null,
        ]);
    }

    /**
     * PUT /api/v1/visitors/{id}/check-in — check in a visitor.
     */
    public function checkIn(string $id, Request $request): JsonResponse
    {
        $visitor = Visitor::where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('host_user_id', $request->user()->id);
                if (in_array($request->user()->role, ['admin', 'superadmin'])) {
                    $q->orWhereNotNull('id');
                }
            })
            ->firstOrFail();

        if ($visitor->status !== 'pending') {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['message' => 'Visitor is not in pending status', 'code' => 'INVALID_STATUS'],
            ], 422);
        }

        $visitor->update([
            'status' => 'checked_in',
            'checked_in_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $visitor->fresh(),
            'error' => null,
        ]);
    }

    /**
     * DELETE /api/v1/visitors/{id} — cancel a visitor registration.
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $visitor = Visitor::where('id', $id)
            ->where(function ($q) use ($request) {
                $q->where('host_user_id', $request->user()->id);
                if (in_array($request->user()->role, ['admin', 'superadmin'])) {
                    $q->orWhereNotNull('id');
                }
            })
            ->firstOrFail();

        $visitor->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'data' => null,
            'error' => null,
        ]);
    }
}
