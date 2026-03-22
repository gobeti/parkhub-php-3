<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * GET /api/v1/bookings/recommendations
     *
     * Suggest optimal parking slots using weighted scoring:
     * - Frequency 40%: past usage of the specific slot
     * - Availability 30%: is the slot available right now
     * - Price 20%: lot hourly rate (lower is better)
     * - Distance 10%: lower slot number = closer to entrance
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $lotId = $request->query('lot_id');

        // 1. Get user's booking history (completed/active/confirmed only)
        $bookings = Booking::where('user_id', $user->id)
            ->whereIn('status', ['active', 'completed', 'confirmed'])
            ->get();

        // 2. Count slot and lot usage frequency
        $slotFrequency = $bookings->groupBy('slot_id')->map->count();
        $lotFrequency = $bookings->groupBy('lot_id')->map->count();

        // 3. Get lots (optionally filtered)
        $lotsQuery = ParkingLot::with(['slots' => function ($q) {
            $q->where('status', 'available')
                ->whereDoesntHave('activeBooking');
        }]);

        if ($lotId) {
            $lotsQuery->where('id', $lotId);
        }

        $lots = $lotsQuery->get();

        // 4. Compute price stats for normalization
        $prices = $lots->pluck('hourly_rate')->filter()->values();
        $maxPrice = $prices->max() ?: 1;

        // 5. Score each available slot with weighted algorithm
        $candidates = collect();

        foreach ($lots as $lot) {
            $lotRate = $lot->hourly_rate ?: 0;

            foreach ($lot->slots as $slot) {
                $score = 0.0;
                $reasons = [];
                $badges = [];

                // Frequency component (40% weight, max 40 points)
                $freq = $slotFrequency->get($slot->id, 0);
                $lotFreq = $lotFrequency->get($lot->id, 0);
                if ($freq > 0) {
                    $score += min($freq, 10) * 4.0; // max 40 points
                    $reasons[] = "Used {$freq} times before";
                    $badges[] = 'your_usual_spot';
                } elseif ($lotFreq > 0) {
                    $score += min($lotFreq, 10) * 2.0; // max 20 points
                    $reasons[] = "In your preferred lot (used {$lotFreq} times)";
                    $badges[] = 'preferred_lot';
                }

                // Availability component (30% weight)
                $score += 30.0;
                $badges[] = 'available_now';
                if (empty($reasons)) {
                    $reasons[] = 'Available now';
                }

                // Price component (20% weight — lower price = higher score)
                if ($maxPrice > 0) {
                    $priceScore = (1 - ($lotRate / $maxPrice)) * 20.0;
                    $score += $priceScore;
                    if ($priceScore >= 15.0) {
                        $badges[] = 'best_price';
                    }
                }

                // Distance component (10% weight — lower slot number = closer)
                $slotNum = (int) $slot->slot_number;
                $distanceScore = 10.0 / max($slotNum, 1);
                $score += $distanceScore;
                if ($distanceScore >= 5.0) {
                    $badges[] = 'closest_entrance';
                }

                // Bonus for accessible slots
                if ($slot->is_accessible ?? false) {
                    $badges[] = 'accessible';
                }

                // Bonus for slot features
                $features = $slot->features ?? [];
                if (! empty($features)) {
                    $score += 5.0;
                    $featureNames = implode(', ', array_map('ucfirst', $features));
                    $reasons[] = "Features: {$featureNames}";
                }

                $candidates->push([
                    'slot_id' => $slot->id,
                    'slot_number' => (int) $slot->slot_number,
                    'lot_id' => $lot->id,
                    'lot_name' => $lot->name,
                    'floor_name' => 'Ground',
                    'score' => round($score, 2),
                    'reasons' => $reasons,
                    'reason_badges' => array_values(array_unique($badges)),
                ]);
            }
        }

        // Sort by score descending, take top 5
        $top = $candidates->sortByDesc('score')->take(5)->values();

        return response()->json([
            'success' => true,
            'data' => $top,
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/recommendations/stats — admin acceptance rate.
     */
    public function stats(): JsonResponse
    {
        $totalBookings = Booking::count();
        $completedBookings = Booking::where('status', 'completed')->count();
        $acceptanceRate = $totalBookings > 0 ? round(($completedBookings / $totalBookings) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_recommendations' => $totalBookings,
                'accepted' => $completedBookings,
                'acceptance_rate' => $acceptanceRate,
                'algorithm_weights' => [
                    'frequency' => 40,
                    'availability' => 30,
                    'price' => 20,
                    'distance' => 10,
                ],
            ],
            'error' => null,
        ]);
    }
}
