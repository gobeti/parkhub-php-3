<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\User;
use App\Support\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    /**
     * GET /api/v1/admin/analytics/overview
     *
     * Comprehensive analytics dashboard with daily bookings (30d),
     * revenue by day, peak hours, top lots, user growth (12mo),
     * and average booking duration.
     *
     * Tenant safety: every Eloquent model here (`Booking`, `User`,
     * `ParkingLot`) carries the `BelongsToTenant` global scope, and
     * we *additionally* pin an explicit `tenant_id` predicate via
     * `->when(TenantScope::currentId(), ...)` on every query so the
     * predicate is visible in the SQL even if someone later drops
     * the trait or chains `withoutGlobalScope(...)`. Platform admins
     * (superadmin + no tenant_id) have `current_tenant` unbound by
     * the `TenantScope` middleware so `currentId()` returns null and
     * both guards no-op — they see cross-tenant rollups as intended.
     */
    public function overview(): JsonResponse
    {
        $driver = DB::getDriverName();
        $tenantId = TenantScope::currentId();
        $scopeBookings = static fn ($q) => $q->when($tenantId !== null, fn ($qq) => $qq->where('tenant_id', $tenantId));
        $scopeBookingsQualified = static fn ($q) => $q->when($tenantId !== null, fn ($qq) => $qq->where('bookings.tenant_id', $tenantId));
        $scopeUsers = static fn ($q) => $q->when($tenantId !== null, fn ($qq) => $qq->where('tenant_id', $tenantId));

        // 1. Daily bookings (last 30 days)
        $dailyBookings = Booking::where('start_time', '>=', now()->subDays(30))
            ->tap($scopeBookings)
            ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => ['date' => $row->date, 'count' => (int) $row->count]);

        // 2. Revenue by day (last 30 days)
        $revenueByDay = Booking::where('start_time', '>=', now()->subDays(30))
            ->whereNotNull('total_price')
            ->where('status', '!=', 'cancelled')
            ->tap($scopeBookings)
            ->selectRaw('DATE(start_time) as date, SUM(total_price) as revenue, COUNT(*) as bookings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'revenue' => round((float) $row->revenue, 2),
                'bookings' => (int) $row->bookings,
            ]);

        // 3. Peak hours (24 bins — bookings grouped by hour of day, last 30 days)
        if ($driver === 'sqlite') {
            $peakHours = Booking::where('start_time', '>=', now()->subDays(30))
                ->tap($scopeBookings)
                ->selectRaw('CAST(strftime("%H", start_time) AS INTEGER) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        } elseif ($driver === 'pgsql') {
            $peakHours = Booking::where('start_time', '>=', now()->subDays(30))
                ->tap($scopeBookings)
                ->selectRaw('EXTRACT(HOUR FROM start_time)::integer as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        } else {
            $peakHours = Booking::where('start_time', '>=', now()->subDays(30))
                ->tap($scopeBookings)
                ->selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }

        // Fill all 24 bins
        $hourMap = $peakHours->pluck('count', 'hour')->all();
        $peakHoursBins = [];
        for ($h = 0; $h < 24; $h++) {
            $peakHoursBins[] = ['hour' => $h, 'count' => (int) ($hourMap[$h] ?? 0)];
        }

        // 4. Top lots by booking count (last 30 days)
        // Bookings carry the tenant predicate via BelongsToTenantScope; we also
        // qualify the filter explicitly on the bookings table so the join result
        // can't pull in parking_lots rows owned by another tenant.
        $topLots = Booking::where('bookings.start_time', '>=', now()->subDays(30))
            ->tap($scopeBookingsQualified)
            ->join('parking_lots', 'bookings.lot_id', '=', 'parking_lots.id')
            ->selectRaw('parking_lots.id, parking_lots.name, COUNT(*) as booking_count, COALESCE(SUM(bookings.total_price), 0) as revenue')
            ->groupBy('parking_lots.id', 'parking_lots.name')
            ->orderByDesc('booking_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'booking_count' => (int) $row->booking_count,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        // 5. User growth (last 12 months)
        if ($driver === 'sqlite') {
            $userGrowth = User::where('created_at', '>=', now()->subMonths(12))
                ->tap($scopeUsers)
                ->selectRaw("strftime('%Y-%m', created_at) as month, COUNT(*) as new_users")
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        } elseif ($driver === 'pgsql') {
            $userGrowth = User::where('created_at', '>=', now()->subMonths(12))
                ->tap($scopeUsers)
                ->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as new_users")
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        } else {
            $userGrowth = User::where('created_at', '>=', now()->subMonths(12))
                ->tap($scopeUsers)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as new_users")
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        }

        $userGrowthData = $userGrowth->map(fn ($row) => [
            'month' => $row->month,
            'new_users' => (int) $row->new_users,
        ]);

        // 6. Average booking duration (in hours, last 30 days)
        if ($driver === 'sqlite') {
            $avgDuration = Booking::where('start_time', '>=', now()->subDays(30))
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->tap($scopeBookings)
                ->selectRaw('AVG((julianday(end_time) - julianday(start_time)) * 24) as avg_hours')
                ->value('avg_hours');
        } elseif ($driver === 'pgsql') {
            $avgDuration = Booking::where('start_time', '>=', now()->subDays(30))
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->tap($scopeBookings)
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (end_time - start_time)) / 3600) as avg_hours')
                ->value('avg_hours');
        } else {
            $avgDuration = Booking::where('start_time', '>=', now()->subDays(30))
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->tap($scopeBookings)
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, start_time, end_time) / 3600) as avg_hours')
                ->value('avg_hours');
        }

        return response()->json([
            'daily_bookings' => $dailyBookings,
            'revenue_by_day' => $revenueByDay,
            'peak_hours' => $peakHoursBins,
            'top_lots' => $topLots,
            'user_growth' => $userGrowthData,
            'avg_duration_hours' => $avgDuration ? round((float) $avgDuration, 2) : 0,
            'total_users' => User::query()->tap($scopeUsers)->count(),
            'total_lots' => ParkingLot::query()->tap(static fn ($q) => $q->when($tenantId !== null, fn ($qq) => $qq->where('tenant_id', $tenantId)))->count(),
        ]);
    }

    /**
     * GET /api/v1/admin/analytics/occupancy
     *
     * Hourly occupancy rates for the last 7 days, grouped by hour of day.
     * Returns 24 bins (hours 0–23) with average active booking count per hour.
     */
    public function occupancy(): JsonResponse
    {
        $driver = DB::getDriverName();
        $tenantId = TenantScope::currentId();
        $scopeBookings = static fn ($q) => $q->when($tenantId !== null, fn ($qq) => $qq->where('tenant_id', $tenantId));

        if ($driver === 'sqlite') {
            $rows = Booking::where('start_time', '>=', now()->subDays(7))
                ->whereIn('status', ['confirmed', 'active', 'completed'])
                ->tap($scopeBookings)
                ->selectRaw('CAST(strftime("%H", start_time) AS INTEGER) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        } elseif ($driver === 'pgsql') {
            $rows = Booking::where('start_time', '>=', now()->subDays(7))
                ->whereIn('status', ['confirmed', 'active', 'completed'])
                ->tap($scopeBookings)
                ->selectRaw('EXTRACT(HOUR FROM start_time)::integer as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        } else {
            $rows = Booking::where('start_time', '>=', now()->subDays(7))
                ->whereIn('status', ['confirmed', 'active', 'completed'])
                ->tap($scopeBookings)
                ->selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get();
        }

        $hourMap = $rows->pluck('count', 'hour')->all();
        $bins = [];
        for ($h = 0; $h < 24; $h++) {
            $bins[] = ['hour' => $h, 'count' => (int) ($hourMap[$h] ?? 0)];
        }

        return response()->json([
            'occupancy' => $bins,
            'period_days' => 7,
        ]);
    }

    /**
     * GET /api/v1/admin/analytics/revenue
     *
     * Daily revenue summary for the last 30 days.
     * Excludes cancelled bookings.
     */
    public function revenue(): JsonResponse
    {
        $tenantId = TenantScope::currentId();

        $rows = Booking::where('start_time', '>=', now()->subDays(30))
            ->whereNotNull('total_price')
            ->where('status', '!=', 'cancelled')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(start_time) as date, SUM(total_price) as revenue, COUNT(*) as bookings')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'revenue' => round((float) $row->revenue, 2),
                'bookings' => (int) $row->bookings,
            ]);

        return response()->json([
            'revenue' => $rows,
            'period_days' => 30,
            'total_revenue' => round($rows->sum('revenue'), 2),
            'total_bookings' => $rows->sum('bookings'),
        ]);
    }

    /**
     * GET /api/v1/admin/analytics/popular-lots
     *
     * Top 10 parking lots ranked by total booking count (all time).
     */
    public function popularLots(): JsonResponse
    {
        $tenantId = TenantScope::currentId();

        $lots = Booking::query()
            ->when($tenantId !== null, fn ($q) => $q->where('bookings.tenant_id', $tenantId))
            ->join('parking_lots', 'bookings.lot_id', '=', 'parking_lots.id')
            ->selectRaw('parking_lots.id, parking_lots.name, COUNT(*) as booking_count, COALESCE(SUM(bookings.total_price), 0) as revenue')
            ->groupBy('parking_lots.id', 'parking_lots.name')
            ->orderByDesc('booking_count')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'booking_count' => (int) $row->booking_count,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        return response()->json([
            'lots' => $lots,
        ]);
    }
}
