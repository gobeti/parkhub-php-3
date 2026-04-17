<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookingHistoryRequest;
use App\Models\Booking;
use App\Models\ParkingLot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParkingHistoryController extends Controller
{
    /**
     * GET /api/v1/bookings/history — paginated booking history with filters.
     */
    public function history(BookingHistoryRequest $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 10);
        $page = (int) $request->query('page', 1);

        $query = Booking::where('user_id', $request->user()->id)
            ->whereIn('status', ['completed', 'cancelled'])
            ->orderByDesc('start_time');

        if ($request->filled('lot_id')) {
            $query->where('lot_id', $request->query('lot_id'));
        }

        if ($request->filled('from')) {
            $query->where('start_time', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->where('end_time', '<=', $request->query('to'));
        }

        $total = $query->count();
        $totalPages = max(1, (int) ceil($total / $perPage));

        $items = $query
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'user_id' => $b->user_id,
                'lot_id' => $b->lot_id,
                'slot_id' => $b->slot_id,
                'lot_name' => $b->lot_name ?? $b->parkingLot?->name ?? 'Unknown',
                'slot_number' => $b->slot_number ?? $b->parkingSlot?->slot_number ?? '?',
                'start_time' => $b->start_time,
                'end_time' => $b->end_time,
                'status' => $b->status,
                'total_price' => $b->total_price,
                'currency' => $b->currency ?? 'EUR',
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items->values(),
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    /**
     * GET /api/v1/bookings/stats — personal parking statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $completedBookings = Booking::where('user_id', $userId)
            ->where('status', 'completed');

        $totalBookings = $completedBookings->count();

        // Favorite lot — most frequently used
        $favoriteLot = Booking::where('user_id', $userId)
            ->where('status', 'completed')
            ->select('lot_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('lot_id')
            ->orderByDesc('cnt')
            ->first();

        $favoriteLotName = null;
        if ($favoriteLot) {
            $lot = ParkingLot::find($favoriteLot->lot_id);
            $favoriteLotName = $lot?->name;
        }

        // Average duration in minutes
        $avgDuration = Booking::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get()
            ->avg(function ($b) {
                $start = Carbon::parse($b->start_time);
                $end = Carbon::parse($b->end_time);

                return $start->diffInMinutes($end);
            }) ?? 0;

        // Busiest day of week
        $busiestDay = Booking::where('user_id', $userId)
            ->where('status', 'completed')
            ->get()
            ->groupBy(fn ($b) => Carbon::parse($b->start_time)->format('l'))
            ->sortByDesc(fn ($group) => $group->count())
            ->keys()
            ->first();

        // Credits spent (sum of total_price for completed bookings)
        $creditsSpent = (int) Booking::where('user_id', $userId)
            ->where('status', 'completed')
            ->sum('total_price');

        // Monthly trend — last 6 months
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $count = Booking::where('user_id', $userId)
                ->where('status', 'completed')
                ->whereYear('start_time', $month->year)
                ->whereMonth('start_time', $month->month)
                ->count();
            $monthlyTrend[] = [
                'month' => $month->format('Y-m'),
                'bookings' => $count,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_bookings' => $totalBookings,
                'favorite_lot' => $favoriteLotName,
                'avg_duration_minutes' => round($avgDuration, 1),
                'busiest_day' => $busiestDay,
                'credits_spent' => $creditsSpent,
                'monthly_trend' => $monthlyTrend,
            ],
        ]);
    }
}
