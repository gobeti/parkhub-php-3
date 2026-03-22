<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MaintenanceWindow;
use App\Models\Notification;
use App\Models\ParkingLot;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WidgetController extends Controller
{
    private const WIDGET_TYPES = [
        'occupancy_chart',
        'revenue_summary',
        'recent_bookings',
        'user_growth',
        'booking_heatmap',
        'active_alerts',
        'maintenance_status',
        'ev_charging_status',
    ];

    /**
     * GET /api/v1/admin/widgets — get user's widget layout.
     */
    public function index(Request $request): JsonResponse
    {
        $key = 'widget_layout_'.$request->user()->id;
        $stored = Setting::get($key);

        if ($stored) {
            $layout = json_decode($stored, true);
        } else {
            // Default layout: first 4 widgets visible
            $layout = [
                'user_id' => $request->user()->id,
                'widgets' => array_map(fn ($type, $idx) => [
                    'id' => 'w'.($idx + 1),
                    'widget_type' => $type,
                    'position' => ['x' => ($idx % 2) * 6, 'y' => intdiv($idx, 2) * 4, 'w' => 6, 'h' => 4],
                    'visible' => $idx < 4,
                ], self::WIDGET_TYPES, array_keys(self::WIDGET_TYPES)),
            ];
        }

        return response()->json($layout);
    }

    /**
     * PUT /api/v1/admin/widgets — save widget layout.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|string',
            'widgets.*.widget_type' => 'required|string|in:'.implode(',', self::WIDGET_TYPES),
            'widgets.*.position' => 'required|array',
            'widgets.*.visible' => 'required|boolean',
        ]);

        $layout = [
            'user_id' => $request->user()->id,
            'widgets' => $request->input('widgets'),
        ];

        $key = 'widget_layout_'.$request->user()->id;
        Setting::set($key, json_encode($layout));

        return response()->json($layout);
    }

    /**
     * GET /api/v1/admin/widgets/data/{widget_id} — widget data by type.
     */
    public function data(Request $request, string $widgetId): JsonResponse
    {
        if (! in_array($widgetId, self::WIDGET_TYPES)) {
            return response()->json(['error' => 'Unknown widget type'], 404);
        }

        $data = match ($widgetId) {
            'occupancy_chart' => $this->occupancyChart(),
            'revenue_summary' => $this->revenueSummary(),
            'recent_bookings' => $this->recentBookings(),
            'user_growth' => $this->userGrowth(),
            'booking_heatmap' => $this->bookingHeatmap(),
            'active_alerts' => $this->activeAlerts(),
            'maintenance_status' => $this->maintenanceStatus(),
            'ev_charging_status' => $this->evChargingStatus(),
        };

        return response()->json([
            'widget_id' => $widgetId,
            'data' => $data,
        ]);
    }

    private function occupancyChart(): array
    {
        $lots = ParkingLot::select('name', 'total_slots', 'available_slots')->get();

        return [
            'lots' => $lots->map(fn ($lot) => [
                'name' => $lot->name,
                'total' => $lot->total_slots,
                'occupied' => $lot->total_slots - $lot->available_slots,
                'percent' => $lot->total_slots > 0
                    ? round(($lot->total_slots - $lot->available_slots) / $lot->total_slots * 100, 1)
                    : 0,
            ])->toArray(),
        ];
    }

    private function revenueSummary(): array
    {
        $thisMonth = Booking::where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_price');

        $lastMonth = Booking::where('status', '!=', 'cancelled')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total_price');

        return [
            'this_month' => round((float) $thisMonth, 2),
            'last_month' => round((float) $lastMonth, 2),
            'change_percent' => $lastMonth > 0
                ? round(($thisMonth - $lastMonth) / $lastMonth * 100, 1)
                : 0,
        ];
    }

    private function recentBookings(): array
    {
        return Booking::with('user')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'user_name' => $b->user?->name,
                'lot_name' => $b->lot_name,
                'start_time' => $b->start_time,
                'status' => $b->status,
            ])
            ->toArray();
    }

    private function userGrowth(): array
    {
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = User::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();
            $months->push([
                'month' => $date->format('Y-m'),
                'new_users' => $count,
            ]);
        }

        return [
            'total_users' => User::count(),
            'monthly' => $months->toArray(),
        ];
    }

    private function bookingHeatmap(): array
    {
        $data = [];
        for ($dow = 0; $dow < 7; $dow++) {
            for ($hour = 6; $hour < 22; $hour++) {
                $data[] = [
                    'day' => $dow,
                    'hour' => $hour,
                    'count' => rand(0, 20), // In production: aggregate from bookings
                ];
            }
        }

        return ['heatmap' => $data];
    }

    private function activeAlerts(): array
    {
        $alerts = Notification::where('notification_type', 'system')
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get(['title', 'message', 'created_at'])
            ->toArray();

        return ['alerts' => $alerts, 'count' => count($alerts)];
    }

    private function maintenanceStatus(): array
    {
        if (! class_exists(MaintenanceWindow::class)) {
            return ['windows' => [], 'active_count' => 0];
        }

        try {
            $active = MaintenanceWindow::where('start_time', '<=', now())
                ->where('end_time', '>=', now())
                ->get(['id', 'lot_id', 'reason', 'start_time', 'end_time'])
                ->toArray();
        } catch (\Exception $e) {
            $active = [];
        }

        return ['windows' => $active, 'active_count' => count($active)];
    }

    private function evChargingStatus(): array
    {
        // EV charging widget data — simplified
        try {
            $evSlots = DB::table('parking_slots')
                ->where('slot_type', 'electric')
                ->selectRaw("count(*) as total, sum(case when status = 'available' then 1 else 0 end) as available")
                ->first();
        } catch (\Exception $e) {
            $evSlots = (object) ['total' => 0, 'available' => 0];
        }

        return [
            'total_stations' => (int) ($evSlots->total ?? 0),
            'available' => (int) ($evSlots->available ?? 0),
            'in_use' => (int) (($evSlots->total ?? 0) - ($evSlots->available ?? 0)),
        ];
    }
}
