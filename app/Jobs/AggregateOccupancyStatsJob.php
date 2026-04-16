<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AggregateOccupancyStatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 60;

    /**
     * @param  string|null  $date  Date to aggregate (Y-m-d). Defaults to yesterday.
     */
    public function __construct(
        private ?string $date = null,
    ) {}

    public function handle(): void
    {
        $date = $this->date ?? now()->subDay()->toDateString();
        $dayStart = $date.' 00:00:00';
        $dayEnd = $date.' 23:59:59';

        $totalSlots = ParkingSlot::count();
        $lots = ParkingLot::all();

        $bookingsForDay = Booking::where('start_time', '<=', $dayEnd)
            ->where('end_time', '>=', $dayStart)
            ->whereNotIn('status', [Booking::STATUS_CANCELLED])
            ->get();

        $totalBookings = $bookingsForDay->count();
        $uniqueUsers = $bookingsForDay->pluck('user_id')->unique()->count();

        // Peak occupancy: sample each hour and find the max
        $peakOccupancy = 0;
        $peakHour = 0;
        $hourlyOccupancy = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $timepoint = $date.' '.str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':30:00';

            $occupied = $bookingsForDay->filter(function ($b) use ($timepoint) {
                return $b->start_time <= $timepoint && $b->end_time >= $timepoint;
            })->count();

            $hourlyOccupancy[$hour] = $occupied;

            if ($occupied > $peakOccupancy) {
                $peakOccupancy = $occupied;
                $peakHour = $hour;
            }
        }

        $avgOccupancy = $totalSlots > 0
            ? round(collect($hourlyOccupancy)->avg() / $totalSlots * 100, 1)
            : 0;

        $peakPercent = $totalSlots > 0
            ? round($peakOccupancy / $totalSlots * 100, 1)
            : 0;

        // Per-lot breakdown
        $lotStats = [];
        foreach ($lots as $lot) {
            $lotSlots = ParkingSlot::where('lot_id', $lot->id)->count();
            $lotBookings = $bookingsForDay->where('lot_id', $lot->id)->count();
            $lotStats[$lot->id] = [
                'lot_name' => $lot->name,
                'total_slots' => $lotSlots,
                'bookings' => $lotBookings,
                'utilization' => $lotSlots > 0 ? round($lotBookings / $lotSlots * 100, 1) : 0,
            ];
        }

        $stats = [
            'date' => $date,
            'total_slots' => $totalSlots,
            'total_bookings' => $totalBookings,
            'unique_users' => $uniqueUsers,
            'peak_occupancy' => $peakOccupancy,
            'peak_hour' => $peakHour,
            'peak_percent' => $peakPercent,
            'avg_occupancy_percent' => $avgOccupancy,
            'hourly_occupancy' => $hourlyOccupancy,
            'lot_stats' => $lotStats,
            'computed_at' => now()->toIso8601String(),
        ];

        // Store in cache with 7-day TTL (keyed by date for admin dashboard retrieval)
        Cache::put("occupancy_stats:{$date}", $stats, now()->addDays(7));

        // Also store the latest date pointer for quick lookup
        Cache::put('occupancy_stats:latest_date', $date, now()->addDays(7));

        Log::info("AggregateOccupancyStatsJob: computed stats for {$date}", [
            'bookings' => $totalBookings,
            'peak_occupancy' => $peakOccupancy,
            'avg_percent' => $avgOccupancy,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AggregateOccupancyStatsJob: failed', [
            'date' => $this->date,
            'error' => $e->getMessage(),
        ]);
    }
}
