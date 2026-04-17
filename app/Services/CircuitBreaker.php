<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\CircuitBreakerOpenException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Per-host circuit breaker (in-process + cache-backed).
 *
 * State machine:
 *   CLOSED   → normal, requests pass through. Counts consecutive failures.
 *              After FAILURE_THRESHOLD failures in FAILURE_WINDOW seconds, trips to OPEN.
 *   OPEN     → all requests rejected with CircuitBreakerOpenException.
 *              After RESET_AFTER seconds, transitions to HALF_OPEN on the next attempt.
 *   HALF_OPEN→ one probe request allowed through. Success → CLOSED, failure → OPEN.
 *
 * Keyed by host (scheme stripped, port preserved) so one flaky webhook target
 * does not trip breakers for unrelated operators.
 *
 * Hand-rolled — no new composer dependency.
 */
class CircuitBreaker
{
    public const STATE_CLOSED = 'closed';

    public const STATE_OPEN = 'open';

    public const STATE_HALF_OPEN = 'half_open';

    /** Consecutive failures required to trip breaker from CLOSED → OPEN. */
    public const FAILURE_THRESHOLD = 5;

    /** Rolling window (seconds) within which failures must occur to count. */
    public const FAILURE_WINDOW = 60;

    /** Seconds before an OPEN breaker allows a HALF_OPEN probe. */
    public const RESET_AFTER = 30;

    /** Cache TTL for state entries (must comfortably exceed RESET_AFTER). */
    private const CACHE_TTL = 600;

    /**
     * Check whether the breaker allows a request to proceed for the given host.
     *
     * OPEN + not yet past reset_after → throws CircuitBreakerOpenException.
     * OPEN + past reset_after         → transitions to HALF_OPEN and returns.
     * Otherwise                       → returns (CLOSED or HALF_OPEN probe).
     *
     * @throws CircuitBreakerOpenException
     */
    public function guard(string $host): void
    {
        $host = $this->normalize($host);
        $state = $this->getState($host);

        if ($state === self::STATE_OPEN) {
            $openedAt = (int) Cache::get($this->openedAtKey($host), 0);
            $elapsed = time() - $openedAt;

            if ($elapsed < self::RESET_AFTER) {
                $this->emitEvent('rejected', $host);

                throw new CircuitBreakerOpenException($host, self::RESET_AFTER - $elapsed);
            }

            // Past reset window → allow a single probe.
            $this->transitionTo($host, self::STATE_HALF_OPEN);
        }
    }

    /**
     * Record a successful outbound request.
     *
     * HALF_OPEN → CLOSED (probe succeeded, recovery confirmed).
     * CLOSED    → resets failure counter.
     */
    public function recordSuccess(string $host): void
    {
        $host = $this->normalize($host);
        $state = $this->getState($host);

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo($host, self::STATE_CLOSED);
            $this->clearFailures($host);

            return;
        }

        // CLOSED success → clear counter if any.
        $this->clearFailures($host);
    }

    /**
     * Record a failed outbound request.
     *
     * HALF_OPEN → OPEN (probe failed, extend outage).
     * CLOSED    → increment counter; if threshold crossed, trip to OPEN.
     */
    public function recordFailure(string $host): void
    {
        $host = $this->normalize($host);
        $state = $this->getState($host);

        if ($state === self::STATE_HALF_OPEN) {
            $this->transitionTo($host, self::STATE_OPEN);
            Cache::put($this->openedAtKey($host), time(), self::CACHE_TTL);

            return;
        }

        // CLOSED: bump failure counter inside rolling window.
        $failures = (int) Cache::get($this->failuresKey($host), 0);
        $failures++;
        Cache::put($this->failuresKey($host), $failures, self::FAILURE_WINDOW);

        if ($failures >= self::FAILURE_THRESHOLD) {
            $this->transitionTo($host, self::STATE_OPEN);
            Cache::put($this->openedAtKey($host), time(), self::CACHE_TTL);
        }
    }

    /**
     * Current breaker state for a host.
     */
    public function getState(string $host): string
    {
        return (string) Cache::get($this->stateKey($this->normalize($host)), self::STATE_CLOSED);
    }

    /**
     * Current consecutive failure count (CLOSED state only — OPEN/HALF_OPEN = 0).
     */
    public function getFailures(string $host): int
    {
        return (int) Cache::get($this->failuresKey($this->normalize($host)), 0);
    }

    /**
     * Force reset — used by admin ops and tests.
     */
    public function reset(string $host): void
    {
        $host = $this->normalize($host);
        Cache::forget($this->stateKey($host));
        Cache::forget($this->failuresKey($host));
        Cache::forget($this->openedAtKey($host));
    }

    /**
     * Extract a breaker key from a URL (host + port).
     */
    public function hostFromUrl(string $url): string
    {
        $parsed = parse_url($url);
        if ($parsed === false || ! isset($parsed['host'])) {
            // Fallback to the raw URL — keeps malformed targets isolated from
            // well-formed ones instead of grouping them under an empty key.
            return $this->normalize($url);
        }

        $host = $parsed['host'];
        if (isset($parsed['port'])) {
            $host .= ':'.$parsed['port'];
        }

        return $this->normalize($host);
    }

    private function transitionTo(string $host, string $newState): void
    {
        $previous = $this->getState($host);
        if ($previous === $newState) {
            return;
        }

        Cache::put($this->stateKey($host), $newState, self::CACHE_TTL);

        Log::info('circuit_breaker.transition', [
            'host' => $host,
            'from' => $previous,
            'to' => $newState,
        ]);

        $this->emitEvent("transition_to_{$newState}", $host);
    }

    private function clearFailures(string $host): void
    {
        Cache::forget($this->failuresKey($host));
    }

    private function emitEvent(string $event, string $host): void
    {
        // Counter accumulator consumed by MetricsController. Stored as a
        // tiny keyed map {"host|event" => count} so the metrics endpoint can
        // emit one Prometheus series per (host, event) pair.
        $key = 'cb:events';
        /** @var array<string,int> $events */
        $events = Cache::get($key, []);
        $bucket = $host.'|'.$event;
        $events[$bucket] = ($events[$bucket] ?? 0) + 1;
        Cache::put($key, $events, self::CACHE_TTL);
    }

    private function stateKey(string $host): string
    {
        return "cb:{$host}:state";
    }

    private function failuresKey(string $host): string
    {
        return "cb:{$host}:failures";
    }

    private function openedAtKey(string $host): string
    {
        return "cb:{$host}:opened_at";
    }

    /**
     * Normalize a host string: lowercase, strip trailing dots/slashes.
     */
    private function normalize(string $host): string
    {
        return strtolower(trim($host, " \t\n\r\0\x0B./"));
    }
}
