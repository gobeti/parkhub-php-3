<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AggregateSystemMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    public function handle(): void
    {
        $now = now();
        $metrics = [];

        // ── Request counts (approximated via personal_access_tokens activity) ──
        try {
            $metrics['requests_last_hour'] = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', $now->copy()->subHour())
                ->count();
            $metrics['requests_last_day'] = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', $now->copy()->subDay())
                ->count();
        } catch (\Exception $e) {
            $metrics['requests_last_hour'] = 0;
            $metrics['requests_last_day'] = 0;
        }

        // ── Slow queries (>100ms) — sample from SQLite query log if enabled ──
        // SQLite doesn't have a built-in slow query log, so we track via
        // DB::listen in a real setup. For now, report 0 and let the
        // SlowQueryListener (if registered) push into cache.
        $metrics['slow_queries'] = (int) Cache::get('system_metrics:slow_query_count', 0);

        // ── Failed jobs ──
        try {
            $metrics['failed_jobs_total'] = DB::table('failed_jobs')->count();
            $metrics['failed_jobs_last_hour'] = DB::table('failed_jobs')
                ->where('failed_at', '>=', $now->copy()->subHour())
                ->count();
        } catch (\Exception $e) {
            $metrics['failed_jobs_total'] = 0;
            $metrics['failed_jobs_last_hour'] = 0;
        }

        // ── Cache hit rate ──
        $cacheHits = (int) Cache::get('system_metrics:cache_hits', 0);
        $cacheMisses = (int) Cache::get('system_metrics:cache_misses', 0);
        $cacheTotal = $cacheHits + $cacheMisses;
        $metrics['cache_hit_rate'] = $cacheTotal > 0
            ? round($cacheHits / $cacheTotal * 100, 1)
            : null;
        $metrics['cache_hits'] = $cacheHits;
        $metrics['cache_misses'] = $cacheMisses;

        // ── Active users (sessions — tokens used in last hour) ──
        try {
            $metrics['active_users'] = DB::table('personal_access_tokens')
                ->where('last_used_at', '>=', $now->copy()->subHour())
                ->distinct('tokenable_id')
                ->count('tokenable_id');
        } catch (\Exception $e) {
            $metrics['active_users'] = 0;
        }

        // ── Total users ──
        $metrics['total_users'] = User::count();

        // ── Queue depth ──
        try {
            $metrics['queue_depth'] = DB::table('jobs')->count();
            $metrics['queue_depth_default'] = DB::table('jobs')
                ->where('queue', 'default')
                ->count();
        } catch (\Exception $e) {
            $metrics['queue_depth'] = 0;
            $metrics['queue_depth_default'] = 0;
        }

        // ── Bookings today ──
        $metrics['bookings_today'] = Booking::whereDate('start_time', today())->count();
        $metrics['active_bookings'] = Booking::where('status', 'confirmed')
            ->where('start_time', '<=', $now)
            ->where('end_time', '>=', $now)
            ->count();

        // ── Memory / CPU from /proc (Linux only) ──
        $metrics['memory'] = $this->getMemoryInfo();
        $metrics['cpu'] = $this->getCpuInfo();

        // ── PHP process memory ──
        $metrics['php_memory_usage_mb'] = round(memory_get_usage(true) / 1024 / 1024, 1);
        $metrics['php_memory_peak_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 1);

        // ── Database size (SQLite) ──
        $metrics['database'] = $this->getDatabaseInfo();

        // ── Uptime ──
        $metrics['uptime_seconds'] = $this->getUptime();

        $metrics['collected_at'] = $now->toIso8601String();

        // Store in cache with 10-minute TTL (refreshed every 5 min by scheduler)
        Cache::put('system_metrics', $metrics, now()->addMinutes(10));

        // Reset rolling counters for next cycle
        Cache::put('system_metrics:cache_hits', 0, now()->addMinutes(10));
        Cache::put('system_metrics:cache_misses', 0, now()->addMinutes(10));
        Cache::put('system_metrics:slow_query_count', 0, now()->addMinutes(10));

        Log::debug('AggregateSystemMetricsJob: collected system metrics', [
            'active_users' => $metrics['active_users'],
            'queue_depth' => $metrics['queue_depth'],
            'failed_jobs' => $metrics['failed_jobs_total'],
        ]);
    }

    private function getMemoryInfo(): ?array
    {
        if (! is_readable('/proc/meminfo')) {
            return null;
        }

        try {
            $meminfo = file_get_contents('/proc/meminfo');
            $lines = explode("\n", $meminfo);
            $data = [];
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s+(\d+)\s+kB/', $line, $m)) {
                    $data[$m[1]] = (int) $m[2];
                }
            }

            $total = $data['MemTotal'] ?? 0;
            $available = $data['MemAvailable'] ?? 0;
            $used = $total - $available;

            return [
                'total_mb' => round($total / 1024, 0),
                'used_mb' => round($used / 1024, 0),
                'available_mb' => round($available / 1024, 0),
                'usage_percent' => $total > 0 ? round($used / $total * 100, 1) : 0,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getCpuInfo(): ?array
    {
        if (! is_readable('/proc/loadavg')) {
            return null;
        }

        try {
            $loadavg = trim(file_get_contents('/proc/loadavg'));
            $parts = explode(' ', $loadavg);

            $cores = 1;
            if (is_readable('/proc/cpuinfo')) {
                $cpuinfo = file_get_contents('/proc/cpuinfo');
                $cores = max(1, substr_count($cpuinfo, 'processor'));
            }

            return [
                'load_1m' => (float) ($parts[0] ?? 0),
                'load_5m' => (float) ($parts[1] ?? 0),
                'load_15m' => (float) ($parts[2] ?? 0),
                'cores' => $cores,
                'load_percent' => $cores > 0 ? round(((float) ($parts[0] ?? 0)) / $cores * 100, 1) : 0,
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getDatabaseInfo(): ?array
    {
        try {
            $connection = config('database.default');
            if ($connection === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if ($dbPath && file_exists($dbPath)) {
                    $sizeBytes = filesize($dbPath);

                    return [
                        'driver' => 'sqlite',
                        'size_mb' => round($sizeBytes / 1024 / 1024, 2),
                        'path' => $dbPath,
                    ];
                }
            }

            return ['driver' => $connection];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getUptime(): ?int
    {
        if (! is_readable('/proc/uptime')) {
            return null;
        }

        try {
            $uptime = trim(file_get_contents('/proc/uptime'));
            $parts = explode(' ', $uptime);

            return (int) ((float) $parts[0]);
        } catch (\Exception $e) {
            return null;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AggregateSystemMetricsJob: failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
