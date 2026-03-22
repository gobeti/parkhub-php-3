<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DemoController extends Controller
{
    private const VOTE_THRESHOLD = 3;

    private const TIMER_DURATION = 1800; // 30 minutes in seconds

    private const CACHE_PREFIX = 'demo_';

    private const AUTO_RESET_INTERVAL_HOURS = 6;

    public function status(): JsonResponse
    {
        if (! config('parkhub.demo_mode')) {
            return response()->json(['error' => 'Demo mode is not enabled'], 403);
        }

        $startedAt = Cache::get(self::CACHE_PREFIX.'started_at', now()->timestamp);
        if (! Cache::has(self::CACHE_PREFIX.'started_at')) {
            Cache::put(self::CACHE_PREFIX.'started_at', $startedAt, self::TIMER_DURATION);
        }

        $elapsed = now()->timestamp - $startedAt;
        $remaining = max(0, self::TIMER_DURATION - $elapsed);

        // Track viewers (unique IPs in last 5 min)
        $viewerKey = self::CACHE_PREFIX.'viewers';
        $viewers = Cache::get($viewerKey, []);
        $ip = request()->ip();
        $viewers[$ip] = now()->timestamp;
        // Prune stale viewers (>5 min)
        $viewers = array_filter($viewers, fn ($ts) => now()->timestamp - $ts < 300);
        Cache::put($viewerKey, $viewers, 600);

        $votes = Cache::get(self::CACHE_PREFIX.'votes', []);
        $hasVoted = isset($votes[$ip]);

        // Reset tracking
        $lastResetAt = Cache::get(self::CACHE_PREFIX.'last_reset_at');
        $nextScheduledReset = Cache::get(self::CACHE_PREFIX.'next_scheduled_reset');
        $resetInProgress = (bool) Cache::get(self::CACHE_PREFIX.'reset_in_progress', false);

        return response()->json([
            'enabled' => true,
            'timer' => [
                'remaining' => $remaining,
                'duration' => self::TIMER_DURATION,
                'started_at' => $startedAt,
            ],
            'votes' => [
                'current' => count($votes),
                'threshold' => self::VOTE_THRESHOLD,
                'has_voted' => $hasVoted,
            ],
            'viewers' => count($viewers),
            'last_reset_at' => $lastResetAt ? date('c', $lastResetAt) : null,
            'next_scheduled_reset' => $nextScheduledReset ? date('c', $nextScheduledReset) : null,
            'reset_in_progress' => $resetInProgress,
        ]);
    }

    public function vote(Request $request): JsonResponse
    {
        if (! config('parkhub.demo_mode')) {
            return response()->json(['error' => 'Demo mode is not enabled'], 403);
        }

        $ip = $request->ip();
        $votes = Cache::get(self::CACHE_PREFIX.'votes', []);

        if (isset($votes[$ip])) {
            return response()->json([
                'message' => 'Already voted',
                'votes' => count($votes),
                'threshold' => self::VOTE_THRESHOLD,
            ]);
        }

        $votes[$ip] = now()->timestamp;
        Cache::put(self::CACHE_PREFIX.'votes', $votes, self::TIMER_DURATION);

        // Check if threshold reached
        if (count($votes) >= self::VOTE_THRESHOLD) {
            return $this->performReset();
        }

        return response()->json([
            'message' => 'Vote recorded',
            'votes' => count($votes),
            'threshold' => self::VOTE_THRESHOLD,
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        if (! config('parkhub.demo_mode')) {
            return response()->json(['error' => 'Demo mode is not enabled'], 403);
        }

        // If an authenticated admin is making the request, allow it directly
        // Otherwise, enforce the solo viewer check for anonymous demo users
        $isAdmin = $request->user() && $request->user()->isAdmin();

        // Only allow solo reset when 1 or fewer active viewers
        $viewers = Cache::get(self::CACHE_PREFIX.'viewers', []);
        $viewers = array_filter($viewers, fn ($ts) => now()->timestamp - $ts < 300);

        if (! $isAdmin && count($viewers) > 1) {
            return response()->json([
                'error' => 'Solo reset not available with multiple viewers. Use voting instead.',
                'viewers' => count($viewers),
            ], 409);
        }

        return $this->performReset();
    }

    private function performReset(): JsonResponse
    {
        // Mark reset in progress
        Cache::put(self::CACHE_PREFIX.'reset_in_progress', true, 300);

        // Clear demo state
        Cache::forget(self::CACHE_PREFIX.'votes');
        Cache::forget(self::CACHE_PREFIX.'started_at');

        // Re-seed demo data: drop all tables, re-run migrations, then seed
        // Use migrate:fresh (not refresh) to avoid partial rollback failures
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => 'ProductionSimulationSeeder', '--force' => true]);
        } catch (\Exception $e) {
            Log::error('Demo reset failed: '.$e->getMessage());
            Cache::forget(self::CACHE_PREFIX.'reset_in_progress');

            return response()->json([
                'error' => 'RESET_FAILED',
                'message' => 'Demo reset failed. Please try again.',
            ], 500);
        }

        // Update reset tracking
        $now = now()->timestamp;
        Cache::put(self::CACHE_PREFIX.'last_reset_at', $now, 86400);
        Cache::put(self::CACHE_PREFIX.'next_scheduled_reset',
            $now + (self::AUTO_RESET_INTERVAL_HOURS * 3600), 86400);
        Cache::forget(self::CACHE_PREFIX.'reset_in_progress');

        return response()->json([
            'message' => 'Demo reset! Page will reload.',
            'reset' => true,
            'votes' => 0,
            'threshold' => self::VOTE_THRESHOLD,
        ]);
    }

    public function config(): JsonResponse
    {
        return response()->json([
            'demo_mode' => (bool) config('parkhub.demo_mode'),
        ]);
    }
}
