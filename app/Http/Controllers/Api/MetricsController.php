<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ParkingLot;
use App\Models\ParkingSlot;
use App\Models\User;
use App\Services\CircuitBreaker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    /**
     * Prometheus text format exposition.
     *
     * Endpoint is protected by an optional bearer token (METRICS_TOKEN env var).
     * Returns Content-Type: text/plain; version=0.0.4 as per Prometheus spec.
     */
    public function index(Request $request): Response
    {
        $expectedToken = config('app.metrics_token');
        if (! $expectedToken || $request->bearerToken() !== $expectedToken) {
            return response('Unauthorized', 401);
        }

        $lines = $this->buildMetrics();

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }

    private function buildMetrics(): array
    {
        $lines = [];
        $now = now();

        // ── Batch scalar counts in fewer queries ────────────────────────────
        $usersTotal = User::count();
        $lotsTotal = ParkingLot::count();

        // Single query for booking counts by status
        $bookingsByStatus = Booking::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        // Active bookings in current time window
        $activeBookings = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->count();

        // Single query for slot counts by status
        $slotsByStatus = ParkingSlot::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');
        $slotsTotal = $slotsByStatus->sum();
        $slotsAvailable = (int) ($slotsByStatus['available'] ?? 0);
        $slotsActive = (int) ($slotsByStatus['active'] ?? 0);

        // ── parkhub_users_total ────────────────────────────────────────────
        $lines[] = '# HELP parkhub_users_total Total registered users';
        $lines[] = '# TYPE parkhub_users_total gauge';
        $lines[] = "parkhub_users_total {$usersTotal}";
        $lines[] = '';

        // ── parkhub_bookings_total (by status) ────────────────────────────
        $lines[] = '# HELP parkhub_bookings_total Total bookings by status';
        $lines[] = '# TYPE parkhub_bookings_total gauge';
        foreach ($bookingsByStatus as $status => $count) {
            $lines[] = "parkhub_bookings_total{status=\"{$status}\"} {$count}";
        }
        $lines[] = '';

        // ── parkhub_bookings_active ────────────────────────────────────────
        $lines[] = '# HELP parkhub_bookings_active Number of currently active/confirmed bookings in window';
        $lines[] = '# TYPE parkhub_bookings_active gauge';
        $lines[] = "parkhub_bookings_active {$activeBookings}";
        $lines[] = '';

        // ── parkhub_lots_total ─────────────────────────────────────────────
        $lines[] = '# HELP parkhub_lots_total Total parking lots';
        $lines[] = '# TYPE parkhub_lots_total gauge';
        $lines[] = "parkhub_lots_total {$lotsTotal}";
        $lines[] = '';

        $lines[] = '# HELP parkhub_slots_total Total parking slots';
        $lines[] = '# TYPE parkhub_slots_total gauge';
        $lines[] = "parkhub_slots_total {$slotsTotal}";
        $lines[] = '';

        $lines[] = '# HELP parkhub_slots_available Parking slots with status=available';
        $lines[] = '# TYPE parkhub_slots_available gauge';
        $lines[] = "parkhub_slots_available {$slotsAvailable}";
        $lines[] = '';

        $lines[] = '# HELP parkhub_slots_active Parking slots with status=active (in use)';
        $lines[] = '# TYPE parkhub_slots_active gauge';
        $lines[] = "parkhub_slots_active {$slotsActive}";
        $lines[] = '';

        // ── parkhub_lot_occupancy_percent (per lot) ────────────────────────
        // Compute occupancy dynamically from active bookings instead of stale available_slots column
        $lots = ParkingLot::all(['id', 'name', 'total_slots']);
        $activeByLot = Booking::whereIn('status', ['confirmed', 'active'])
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->selectRaw('lot_id, COUNT(*) as active_count')
            ->groupBy('lot_id')
            ->pluck('active_count', 'lot_id');

        $lines[] = '# HELP parkhub_lot_occupancy_percent Occupancy percentage per parking lot';
        $lines[] = '# TYPE parkhub_lot_occupancy_percent gauge';
        foreach ($lots as $lot) {
            $total = (int) $lot->total_slots;
            $occupied = (int) ($activeByLot[$lot->id] ?? 0);
            $pct = $total > 0 ? round(($occupied / $total) * 100, 2) : 0;
            $safeName = preg_replace('/[^a-zA-Z0-9_]/', '_', $lot->name);
            $lines[] = "parkhub_lot_occupancy_percent{lot_id=\"{$lot->id}\",lot_name=\"{$safeName}\"} {$pct}";
        }
        $lines[] = '';

        // ── parkhub_active_sessions ────────────────────────────────────────
        try {
            $activeSessions = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', now()->subHour())
                ->count();
            $lines[] = '# HELP parkhub_active_sessions Active API sessions (tokens used in last hour)';
            $lines[] = '# TYPE parkhub_active_sessions gauge';
            $lines[] = "parkhub_active_sessions {$activeSessions}";
            $lines[] = '';
        } catch (\Exception) {
            // Skip if table doesn't exist in test environment
        }

        // ── parkhub_php_circuit_breaker_state / _events_total ──────────────
        // State values: 0=closed, 1=half_open, 2=open. Emitted per host whose
        // breaker has been touched since the last cache eviction.
        $breaker = app(CircuitBreaker::class);
        $stateValue = [
            CircuitBreaker::STATE_CLOSED => 0,
            CircuitBreaker::STATE_HALF_OPEN => 1,
            CircuitBreaker::STATE_OPEN => 2,
        ];

        /** @var array<string,int> $events */
        $events = Cache::get('cb:events', []);
        $hosts = [];
        foreach (array_keys($events) as $bucket) {
            [$host] = explode('|', $bucket, 2);
            $hosts[$host] = true;
        }

        $lines[] = '# HELP parkhub_php_circuit_breaker_state Circuit breaker state per host (0=closed, 1=half_open, 2=open)';
        $lines[] = '# TYPE parkhub_php_circuit_breaker_state gauge';
        foreach (array_keys($hosts) as $host) {
            $state = $breaker->getState($host);
            $value = $stateValue[$state] ?? 0;
            $safe = $this->escapeLabel($host);
            $lines[] = "parkhub_php_circuit_breaker_state{host=\"{$safe}\"} {$value}";
        }
        $lines[] = '';

        $lines[] = '# HELP parkhub_php_circuit_breaker_events_total Circuit breaker events per host+event';
        $lines[] = '# TYPE parkhub_php_circuit_breaker_events_total counter';
        foreach ($events as $bucket => $count) {
            $parts = explode('|', $bucket, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$host, $event] = $parts;
            $safeHost = $this->escapeLabel($host);
            $safeEvent = $this->escapeLabel($event);
            $lines[] = "parkhub_php_circuit_breaker_events_total{host=\"{$safeHost}\",event=\"{$safeEvent}\"} {$count}";
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * Escape a label value for Prometheus text exposition.
     */
    private function escapeLabel(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', '\\n'], $value);
    }
}
