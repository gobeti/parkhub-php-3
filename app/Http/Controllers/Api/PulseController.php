<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\AggregateSystemMetricsJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PulseController extends Controller
{
    /**
     * GET /api/admin/pulse — Lightweight system monitoring dashboard.
     *
     * Returns cached system metrics (collected every 5 min by AggregateSystemMetricsJob).
     * If no cached data exists yet, dispatches a fresh collection synchronously.
     */
    public function index(): JsonResponse
    {
        $metrics = Cache::get('system_metrics');

        if (! $metrics) {
            // First request — collect metrics on the spot
            dispatch_sync(new AggregateSystemMetricsJob);
            $metrics = Cache::get('system_metrics');
        }

        if (! $metrics) {
            return response()->json([
                'status' => 'unavailable',
                'message' => 'System metrics not yet available. They will be collected shortly.',
            ], 503);
        }

        return response()->json($metrics);
    }
}
