<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDynamicPricingRequest;
use App\Models\Booking;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynamicPricingController extends Controller
{
    private array $defaultRules = [
        'enabled' => false,
        'base_price' => 2.50,
        'surge_multiplier' => 1.5,
        'discount_multiplier' => 0.8,
        'surge_threshold' => 80,
        'discount_threshold' => 20,
    ];

    /**
     * GET /api/v1/lots/{id}/pricing/dynamic
     * Returns the current dynamic price based on occupancy.
     */
    public function show(string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);
        $rules = array_merge($this->defaultRules, $lot->dynamic_pricing_rules ?? []);

        $totalSlots = $lot->slots()->count();
        $occupied = Booking::where('lot_id', $id)
            ->whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', now())
            ->where('end_time', '>=', now())
            ->count();

        $occupancyPercent = $totalSlots > 0 ? round(($occupied / $totalSlots) * 100) : 0;

        $basePrice = (float) ($rules['base_price'] ?? $lot->hourly_rate ?? 2.50);
        $multiplier = 1.0;
        $tier = 'normal';

        if ($rules['enabled']) {
            if ($occupancyPercent >= $rules['surge_threshold']) {
                $multiplier = (float) $rules['surge_multiplier'];
                $tier = 'surge';
            } elseif ($occupancyPercent <= $rules['discount_threshold']) {
                $multiplier = (float) $rules['discount_multiplier'];
                $tier = 'discount';
            }
        }

        return response()->json([
            'current_price' => round($basePrice * $multiplier, 2),
            'base_price' => $basePrice,
            'applied_multiplier' => $multiplier,
            'occupancy_percent' => $occupancyPercent,
            'dynamic_pricing_active' => $rules['enabled'],
            'tier' => $tier,
            'currency' => $lot->currency ?? 'EUR',
        ]);
    }

    /**
     * GET /api/v1/admin/lots/{id}/pricing/dynamic
     * Admin: get pricing rules for a lot.
     */
    public function adminShow(Request $request, string $id): JsonResponse
    {
        $this->requireAdmin($request);
        $lot = ParkingLot::findOrFail($id);

        return response()->json(
            array_merge($this->defaultRules, $lot->dynamic_pricing_rules ?? [])
        );
    }

    /**
     * PUT /api/v1/admin/lots/{id}/pricing/dynamic
     * Admin: update pricing rules for a lot.
     */
    public function adminUpdate(UpdateDynamicPricingRequest $request, string $id): JsonResponse
    {
        $lot = ParkingLot::findOrFail($id);
        $current = array_merge($this->defaultRules, $lot->dynamic_pricing_rules ?? []);
        $updated = array_merge($current, $request->only([
            'enabled', 'base_price', 'surge_multiplier',
            'discount_multiplier', 'surge_threshold', 'discount_threshold',
        ]));

        $lot->update(['dynamic_pricing_rules' => $updated]);

        return response()->json($updated);
    }

    private function requireAdmin(Request $request): void
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403, 'Admin access required');
        }
    }
}
