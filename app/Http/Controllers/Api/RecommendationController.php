<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * GET /api/v1/bookings/recommendations
     *
     * Suggest optimal parking slots based on user history, favorites, and availability.
     */
    public function index(Request $request)
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

        // 4. Score each available slot
        $candidates = collect();

        foreach ($lots as $lot) {
            foreach ($lot->slots as $slot) {
                $score = 0.0;
                $reasons = [];

                // Factor 1: User's favorite slot (past usage frequency)
                $freq = $slotFrequency->get($slot->id, 0);
                if ($freq > 0) {
                    $score += min($freq, 10) * 4.0; // max 40 points
                    $reasons[] = "Used {$freq} times before";
                }

                // Factor 2: User's preferred lot
                $lotFreq = $lotFrequency->get($lot->id, 0);
                if ($lotFreq > 0) {
                    $score += min($lotFreq, 10) * 2.0; // max 20 points
                    if ($freq === 0) {
                        $reasons[] = "In your preferred lot (used {$lotFreq} times)";
                    }
                }

                // Factor 3: Slot features bonus
                $features = $slot->features ?? [];
                if (! empty($features)) {
                    $score += 5.0;
                    $featureNames = implode(', ', array_map('ucfirst', $features));
                    $reasons[] = "Features: {$featureNames}";
                }

                // Factor 4: Low slot number preference (closer to entrance)
                $slotNum = (int) $slot->slot_number;
                $rowBonus = 10.0 / max($slotNum, 1);
                $score += $rowBonus;

                // Factor 5: Base availability score
                $score += 10.0;
                if (empty($reasons)) {
                    $reasons[] = 'Available now';
                }

                $candidates->push([
                    'slot_id' => $slot->id,
                    'slot_number' => (int) $slot->slot_number,
                    'lot_id' => $lot->id,
                    'lot_name' => $lot->name,
                    'floor_name' => 'Ground',
                    'score' => round($score, 2),
                    'reasons' => $reasons,
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
}
