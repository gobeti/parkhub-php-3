<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class RateDashboardController extends Controller
{
    /**
     * Rate limit group definitions with their configured limits.
     */
    private const GROUPS = [
        'auth' => ['limit' => 5, 'description' => 'Authentication endpoints'],
        'api' => ['limit' => 100, 'description' => 'General API endpoints'],
        'public' => ['limit' => 30, 'description' => 'Public endpoints'],
        'webhook' => ['limit' => 50, 'description' => 'Webhook endpoints'],
    ];

    /**
     * GET /api/v1/admin/rate-limits — per-group rate limit stats.
     */
    public function index(): JsonResponse
    {
        $groups = [];
        $totalBlocked = 0;

        foreach (self::GROUPS as $name => $config) {
            $blockedKey = "rate_limit_blocked:{$name}";
            $blocked = (int) Cache::get($blockedKey, 0);
            $totalBlocked += $blocked;

            $currentKey = "rate_limit_current:{$name}";
            $current = (int) Cache::get($currentKey, 0);

            $groups[] = [
                'group' => $name,
                'limit_per_minute' => $config['limit'],
                'description' => $config['description'],
                'current_count' => $current,
                'reset_seconds' => 60,
                'blocked_last_hour' => $blocked,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'groups' => $groups,
                'total_blocked_last_hour' => $totalBlocked,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/rate-limits/history — 24h hourly bins of blocked requests.
     */
    public function history(): JsonResponse
    {
        $bins = [];
        $now = now();

        for ($i = 23; $i >= 0; $i--) {
            $hour = $now->copy()->subHours($i);
            $key = 'rate_limit_blocked_hour:'.$hour->format('Y-m-d-H');
            $count = (int) Cache::get($key, 0);

            $bins[] = [
                'hour' => $hour->format('Y-m-d\TH:00'),
                'count' => $count,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bins' => $bins,
            ],
        ]);
    }
}
