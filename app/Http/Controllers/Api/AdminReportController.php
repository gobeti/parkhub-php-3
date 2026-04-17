<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HeatmapReportRequest;
use App\Http\Requests\OccupancyReportRequest;
use App\Http\Requests\RevenueReportRequest;
use App\Models\Absence;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Support\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every admin report/export method below is defense-in-depth scoped to
 * the current tenant via `->when(TenantScope::currentId(), ...)` on top
 * of the `BelongsToTenant` global scope. When `MODULE_MULTI_TENANT` is
 * off (or the caller is a platform super-admin with no tenant bound)
 * `currentId()` returns null and the `when()` chain is a no-op, so
 * unscoped global aggregates still flow to platform admins as intended.
 * Tables without a `tenant_id` column (`parking_slots`, `absences`) are
 * scoped transitively through their owning relation (`lot`, `user`)
 * with `whereHas`.
 */
class AdminReportController extends Controller
{
    /**
     * Sanitize a value for CSV output to prevent formula injection.
     * Prefixes dangerous leading characters (=, +, -, @, TAB, CR) with a single quote.
     */
    private function csvSafe(mixed $value): string
    {
        $str = (string) $value;
        if (preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }

    public function stats(Request $request): JsonResponse
    {

        $now = now();
        $tenantId = TenantScope::currentId();

        $activeBookings = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->count();

        // parking_slots has no tenant_id; scope via parent lot when a tenant is bound.
        $totalSlots = ParkingSlot::query()
            ->when(
                $tenantId !== null,
                fn ($q) => $q->whereHas('lot', fn ($lq) => $lq->where('tenant_id', $tenantId))
            )
            ->count();

        return response()->json([
            'total_users' => User::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
            'total_lots' => ParkingLot::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
            'total_slots' => $totalSlots,
            'available_slots' => $totalSlots - $activeBookings,
            'total_bookings' => Booking::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
            'active_bookings' => $activeBookings,
            'occupancy_percent' => $totalSlots > 0 ? round(($activeBookings / $totalSlots) * 100) : 0,
            'homeoffice_today' => Absence::where('absence_type', 'homeoffice')
                ->where('start_date', '<=', $now->toDateString())
                ->where('end_date', '>=', $now->toDateString())
                ->when(
                    $tenantId !== null,
                    fn ($q) => $q->whereHas('user', fn ($uq) => $uq->where('tenant_id', $tenantId))
                )
                ->count(),
            'total_bookings_today' => Booking::whereDate('start_time', $now->toDateString())
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
        ]);
    }

    public function heatmap(HeatmapReportRequest $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);

        // Use DB-agnostic expressions: DAYOFWEEK (MySQL) vs strftime (SQLite)
        $driver = DB::getDriverName();
        $tenantId = TenantScope::currentId();

        $query = Booking::where('start_time', '>=', now()->subDays($days))
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        if ($driver === 'sqlite') {
            $bookings = $query
                ->selectRaw('CAST(strftime("%w", start_time) AS INTEGER) as day_of_week, CAST(strftime("%H", start_time) AS INTEGER) as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: EXTRACT(DOW ...) returns 0=Sunday...6=Saturday (same as SQLite strftime %w)
            $bookings = $query
                ->selectRaw('EXTRACT(DOW FROM start_time)::integer as day_of_week, EXTRACT(HOUR FROM start_time)::integer as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        } else {
            // MySQL / MariaDB (DAYOFWEEK returns 1=Sunday...7=Saturday, normalise to 0=Sunday...6=Saturday)
            $bookings = $query
                ->selectRaw('(DAYOFWEEK(start_time) - 1) as day_of_week, HOUR(start_time) as hour, COUNT(*) as count')
                ->groupBy('day_of_week', 'hour')
                ->get();
        }

        return response()->json($bookings);
    }

    public function reports(Request $request): JsonResponse
    {

        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);
        $tenantId = TenantScope::currentId();

        $baseQuery = Booking::where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        $totalBookings = (clone $baseQuery)->count();

        // Aggregate by day using DB query instead of loading all bookings
        $byDay = (clone $baseQuery)
            ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->all();

        $byStatus = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->all();

        $byBookingType = (clone $baseQuery)
            ->selectRaw('booking_type, COUNT(*) as count')
            ->groupBy('booking_type')
            ->pluck('count', 'booking_type')
            ->all();

        // Compute average duration using DB aggregate
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $avgDuration = (clone $baseQuery)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->selectRaw('AVG((julianday(end_time) - julianday(start_time)) * 24) as avg_hours')
                ->value('avg_hours');
        } else {
            $avgDuration = (clone $baseQuery)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, start_time, end_time) / 3600) as avg_hours')
                ->value('avg_hours');
        }

        return response()->json([
            'period_days' => $days,
            'total_bookings' => $totalBookings,
            'by_day' => $byDay,
            'by_status' => $byStatus,
            'by_booking_type' => $byBookingType,
            'avg_duration_hours' => $avgDuration ? round((float) $avgDuration, 2) : 0,
        ]);
    }

    public function dashboardCharts(Request $request): JsonResponse
    {

        $days = (int) $request->get('days', 7);
        $startDate = now()->subDays($days - 1)->toDateString();
        $tenantId = TenantScope::currentId();

        // Single GROUP BY query instead of N individual count queries.
        $counts = Booking::where('start_time', '>=', $startDate)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $labels = [];
        $bookingCounts = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $labels[] = $date;
            $bookingCounts[] = $counts->get($date, 0);
        }

        return response()->json([
            'booking_trend' => ['labels' => $labels, 'data' => $bookingCounts],
            'occupancy_now' => [
                'total' => ParkingSlot::query()
                    ->when(
                        $tenantId !== null,
                        fn ($q) => $q->whereHas('lot', fn ($lq) => $lq->where('tenant_id', $tenantId))
                    )
                    ->count(),
                'occupied' => Booking::whereIn('status', ['confirmed', 'active'])
                    ->where('start_time', '<=', now())
                    ->where('end_time', '>=', now())
                    ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->count(),
            ],
        ]);
    }

    public function exportBookingsCsv(Request $request): StreamedResponse
    {

        $headers = ['ID', 'User', 'Lot', 'Slot', 'Vehicle', 'Start', 'End', 'Status', 'Type'];
        $tenantId = TenantScope::currentId();

        // Tenant filter MUST be applied to the query builder before the cursor
        // is opened — every row the generator emits flows through the CSV.
        // BelongsToTenantScope handles this when the flag is on; we also pin
        // an explicit predicate so a future withoutGlobalScope(...) wouldn't
        // silently leak cross-tenant rows into the export.
        $query = Booking::with('user')
            ->orderBy('start_time', 'desc')
            ->when($tenantId !== null, fn ($q) => $q->where('bookings.tenant_id', $tenantId));

        return response()->streamDownload(function () use ($headers, $query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            // Stream using cursor to avoid loading all bookings into memory
            foreach ($query->cursor() as $b) {
                fputcsv($output, [
                    $b->id,
                    $this->csvSafe($b->user?->name ?? 'Guest'),
                    $this->csvSafe($b->lot_name),
                    $this->csvSafe($b->slot_number),
                    $this->csvSafe($b->vehicle_plate ?? ''),
                    $b->start_time?->format('Y-m-d H:i'),
                    $b->end_time?->format('Y-m-d H:i'),
                    $b->status,
                    $b->booking_type,
                ]);
            }

            fclose($output);
        }, 'bookings-export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Revenue report grouped by day/week/month.
     */
    public function revenue(RevenueReportRequest $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'day');
        $driver = DB::getDriverName();
        $tenantId = TenantScope::currentId();

        $dateExpr = match ($groupBy) {
            'day' => $driver === 'sqlite'
                ? 'DATE(start_time)'
                : 'DATE(start_time)',
            'week' => $driver === 'sqlite'
                ? "strftime('%Y-W%W', start_time)"
                : 'YEARWEEK(start_time, 3)',
            'month' => $driver === 'sqlite'
                ? "strftime('%Y-%m', start_time)"
                : "DATE_FORMAT(start_time, '%Y-%m')",
        };

        $data = Booking::whereBetween('start_time', [$request->start, $request->end.' 23:59:59'])
            ->whereNotNull('total_price')
            ->where('status', '!=', 'cancelled')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw("{$dateExpr} as period, SUM(total_price) as total_revenue, COUNT(*) as booking_count")
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        $totalRevenue = $data->sum('total_revenue');

        return response()->json([
            'period' => ['start' => $request->start, 'end' => $request->end, 'group_by' => $groupBy],
            'data' => $data,
            'total_revenue' => round($totalRevenue, 2),
            'total_bookings' => $data->sum('booking_count'),
        ]);
    }

    /**
     * Occupancy percentage over time.
     */
    public function occupancy(OccupancyReportRequest $request): JsonResponse
    {
        $tenantId = TenantScope::currentId();
        $totalSlots = ParkingSlot::query()
            ->when(
                $tenantId !== null,
                fn ($q) => $q->whereHas('lot', fn ($lq) => $lq->where('tenant_id', $tenantId))
            )
            ->count();
        if ($totalSlots === 0) {
            return response()->json(['data' => [], 'total_slots' => 0]);
        }

        $data = Booking::whereBetween('start_time', [$request->start, $request->end.' 23:59:59'])
            ->whereIn('status', ['confirmed', 'active', 'completed'])
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(start_time) as date, COUNT(DISTINCT slot_id) as occupied_slots')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'occupied_slots' => $row->occupied_slots,
                'total_slots' => $totalSlots,
                'occupancy_percent' => round(($row->occupied_slots / $totalSlots) * 100, 1),
            ]);

        return response()->json([
            'data' => $data,
            'total_slots' => $totalSlots,
        ]);
    }

    /**
     * User activity report — registrations and active users trend.
     */
    public function usersReport(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $since = now()->subDays($days);
        $tenantId = TenantScope::currentId();

        $registrations = User::where('created_at', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $activeUsers = Booking::where('start_time', '>=', $since)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->selectRaw('DATE(start_time) as date, COUNT(DISTINCT user_id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return response()->json([
            'period_days' => $days,
            'total_users' => User::query()
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
            'new_users' => User::where('created_at', '>=', $since)
                ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
                ->count(),
            'registrations_by_day' => $registrations,
            'active_users_by_day' => $activeUsers,
        ]);
    }

    public function exportUsersCsv(Request $request): Response
    {

        // Defense-in-depth on top of the BelongsToTenant global scope — if
        // the trait were ever removed or a caller chained withoutGlobalScope
        // the export would still stay inside the tenant's row set.
        $tenantId = TenantScope::currentId();
        $users = User::orderBy('name')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $headers = ['ID', 'Username', 'Name', 'Email', 'Role', 'Department', 'Active', 'Created'];
        $rows = $users->map(fn ($u) => [
            $u->id,
            $this->csvSafe($u->username),
            $this->csvSafe($u->name),
            $this->csvSafe($u->email),
            $u->role,
            $this->csvSafe($u->department ?? ''),
            $u->is_active ? 'yes' : 'no',
            optional($u->created_at)->format('Y-m-d'),
        ]);

        $output = fopen('php://output', 'w');
        ob_start();
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-export.csv"',
        ]);
    }
}
