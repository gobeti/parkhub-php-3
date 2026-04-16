<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParkingZoneController extends Controller
{
    /**
     * Pricing tier multipliers.
     */
    private const TIER_MULTIPLIERS = [
        'economy' => 0.8,
        'standard' => 1.0,
        'premium' => 1.5,
        'vip' => 2.0,
    ];

    private const TIER_COLORS = [
        'economy' => '#22c55e',
        'standard' => '#3b82f6',
        'premium' => '#f59e0b',
        'vip' => '#a855f7',
    ];

    /**
     * GET /api/v1/lots/{lotId}/zones/pricing — list zones with pricing tiers.
     */
    public function index(string $lotId): JsonResponse
    {
        $this->ensurePricingColumns();

        $zones = Zone::where('lot_id', $lotId)->get();

        return response()->json([
            'success' => true,
            'data' => $zones->map(fn (Zone $z) => $this->formatZoneWithPricing($z))->values(),
        ]);
    }

    /**
     * POST /api/v1/lots/{lotId}/zones/pricing — create a zone with pricing tier.
     */
    public function store(Request $request, string $lotId): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'color' => 'nullable|string|max:20',
            'tier' => 'required|string|in:economy,standard,premium,vip',
            'pricing_multiplier' => 'nullable|numeric|min:0.1|max:10.0',
            'max_capacity' => 'nullable|integer|min:1',
        ]);

        $this->ensurePricingColumns();

        $tier = $request->input('tier', 'standard');
        $multiplier = $request->input('pricing_multiplier', self::TIER_MULTIPLIERS[$tier] ?? 1.0);

        $zone = Zone::create([
            'lot_id' => $lotId,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'color' => $request->input('color', self::TIER_COLORS[$tier] ?? null),
            'tier' => $tier,
            'pricing_multiplier' => $multiplier,
            'max_capacity' => $request->input('max_capacity'),
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->formatZoneWithPricing($zone),
        ], 201);
    }

    /**
     * PUT /api/v1/admin/zones/{id}/pricing — update zone pricing tier.
     */
    public function updatePricing(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'tier' => 'sometimes|required|string|in:economy,standard,premium,vip',
            'pricing_multiplier' => 'nullable|numeric|min:0.1|max:10.0',
            'max_capacity' => 'nullable|integer|min:1',
        ]);

        $this->ensurePricingColumns();

        $zone = Zone::findOrFail($id);

        $updates = [];

        if ($request->has('tier')) {
            $updates['tier'] = $request->input('tier');
            if (! $request->has('pricing_multiplier')) {
                $updates['pricing_multiplier'] = self::TIER_MULTIPLIERS[$request->input('tier')] ?? 1.0;
            }
        }

        if ($request->has('pricing_multiplier')) {
            $updates['pricing_multiplier'] = $request->input('pricing_multiplier');
        }

        if ($request->has('max_capacity')) {
            $updates['max_capacity'] = $request->input('max_capacity');
        }

        $zone->update($updates);
        $zone->refresh();

        return response()->json([
            'success' => true,
            'data' => $this->formatZoneWithPricing($zone),
        ]);
    }

    /**
     * DELETE /api/v1/lots/{lotId}/zones/{id}/pricing — remove pricing (reset to standard).
     */
    public function destroyPricing(string $lotId, string $id): JsonResponse
    {
        $this->ensurePricingColumns();

        $zone = Zone::where('lot_id', $lotId)->findOrFail($id);
        $zone->update([
            'tier' => 'standard',
            'pricing_multiplier' => 1.0,
            'max_capacity' => null,
        ]);

        return response()->json(['success' => true, 'message' => 'Pricing reset to standard']);
    }

    private function formatZoneWithPricing(Zone $zone): array
    {
        $tier = $zone->tier ?? 'standard';
        $multiplier = $zone->pricing_multiplier ?? self::TIER_MULTIPLIERS[$tier] ?? 1.0;

        return [
            'id' => $zone->id,
            'lot_id' => $zone->lot_id,
            'name' => $zone->name,
            'description' => $zone->description,
            'color' => $zone->color,
            'tier' => $tier,
            'tier_display' => ucfirst($tier),
            'tier_color' => self::TIER_COLORS[$tier] ?? '#3b82f6',
            'pricing_multiplier' => (float) $multiplier,
            'max_capacity' => $zone->max_capacity ? (int) $zone->max_capacity : null,
            'created_at' => $zone->created_at?->toISOString(),
            'updated_at' => $zone->updated_at?->toISOString(),
        ];
    }

    /**
     * Ensure pricing columns exist on the zones table.
     */
    private function ensurePricingColumns(): void
    {
        $schema = Zone::query()->getConnection()->getSchemaBuilder();
        if (! $schema->hasColumn('zones', 'tier')) {
            $schema->table('zones', function ($table) {
                $table->string('tier')->default('standard')->after('description');
                $table->decimal('pricing_multiplier', 5, 2)->default(1.00)->after('tier');
                $table->integer('max_capacity')->nullable()->after('pricing_multiplier');
            });
        }
    }
}
