<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\CircuitBreaker;
use RuntimeException;

/**
 * Thrown when an outbound call targets a host whose circuit breaker is OPEN.
 *
 * The breaker trips after a host produces {@see CircuitBreaker::FAILURE_THRESHOLD}
 * consecutive failures within the failure window. Upstream callers (jobs) catch
 * this and mark themselves as permanently failed for the current attempt rather
 * than hammering the target while it is clearly unhealthy.
 */
class CircuitBreakerOpenException extends RuntimeException
{
    public function __construct(
        public readonly string $host,
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct(
            "Circuit breaker OPEN for host '{$host}'; retry after {$retryAfterSeconds}s"
        );
    }
}
