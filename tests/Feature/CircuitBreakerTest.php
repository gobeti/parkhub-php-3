<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\CircuitBreakerOpenException;
use App\Services\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->breaker = new CircuitBreaker;
    }

    public function test_initial_state_is_closed(): void
    {
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState('example.com'));
        $this->assertSame(0, $this->breaker->getFailures('example.com'));
    }

    public function test_closed_allows_requests(): void
    {
        // Should not throw.
        $this->breaker->guard('example.com');
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState('example.com'));
    }

    public function test_closed_to_open_after_five_failures(): void
    {
        $host = 'bad.example.com';

        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->breaker->getState($host));
    }

    public function test_open_rejects_requests(): void
    {
        $host = 'bad.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }

        $this->expectException(CircuitBreakerOpenException::class);
        $this->breaker->guard($host);
    }

    public function test_failures_below_threshold_stay_closed(): void
    {
        $host = 'flaky.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD - 1; $i++) {
            $this->breaker->recordFailure($host);
        }

        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState($host));
        // Should not throw.
        $this->breaker->guard($host);
    }

    public function test_success_clears_failure_counter(): void
    {
        $host = 'recovering.example.com';
        $this->breaker->recordFailure($host);
        $this->breaker->recordFailure($host);
        $this->assertSame(2, $this->breaker->getFailures($host));

        $this->breaker->recordSuccess($host);
        $this->assertSame(0, $this->breaker->getFailures($host));
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState($host));
    }

    public function test_open_to_half_open_after_reset_window(): void
    {
        $host = 'recovering.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }
        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->breaker->getState($host));

        // Rewind opened_at by RESET_AFTER + 1 seconds to simulate passage of time.
        $normalized = $this->breaker->hostFromUrl("http://{$host}");
        Cache::put(
            "cb:{$normalized}:opened_at",
            time() - CircuitBreaker::RESET_AFTER - 1,
            600
        );

        // guard() should now transition OPEN → HALF_OPEN and not throw.
        $this->breaker->guard($host);
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $this->breaker->getState($host));
    }

    public function test_half_open_to_closed_after_probe_success(): void
    {
        $host = 'healed.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }
        $normalized = $this->breaker->hostFromUrl("http://{$host}");
        Cache::put(
            "cb:{$normalized}:opened_at",
            time() - CircuitBreaker::RESET_AFTER - 1,
            600
        );
        $this->breaker->guard($host);
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $this->breaker->getState($host));

        $this->breaker->recordSuccess($host);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState($host));
        $this->assertSame(0, $this->breaker->getFailures($host));
    }

    public function test_half_open_to_open_after_probe_failure(): void
    {
        $host = 'still-broken.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }
        $normalized = $this->breaker->hostFromUrl("http://{$host}");
        Cache::put(
            "cb:{$normalized}:opened_at",
            time() - CircuitBreaker::RESET_AFTER - 1,
            600
        );
        $this->breaker->guard($host);
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $this->breaker->getState($host));

        $this->breaker->recordFailure($host);
        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->breaker->getState($host));

        // And guard() rejects again.
        $this->expectException(CircuitBreakerOpenException::class);
        $this->breaker->guard($host);
    }

    public function test_per_host_isolation(): void
    {
        $hostA = 'a.example.com';
        $hostB = 'b.example.com';

        // Trip A.
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($hostA);
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->breaker->getState($hostA));
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState($hostB));

        // B's guard still passes.
        $this->breaker->guard($hostB);

        // A's guard throws.
        $this->expectException(CircuitBreakerOpenException::class);
        $this->breaker->guard($hostA);
    }

    public function test_host_from_url_extracts_host_and_port(): void
    {
        $this->assertSame('example.com', $this->breaker->hostFromUrl('https://example.com/webhook'));
        $this->assertSame('example.com:8080', $this->breaker->hostFromUrl('https://example.com:8080/webhook'));
        $this->assertSame('127.0.0.1:1', $this->breaker->hostFromUrl('http://127.0.0.1:1/webhook'));
    }

    public function test_host_normalization_is_case_insensitive(): void
    {
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure('Example.COM');
        }

        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->breaker->getState('example.com'));
    }

    public function test_reset_clears_state(): void
    {
        $host = 'reset.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }
        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->breaker->getState($host));

        $this->breaker->reset($host);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->breaker->getState($host));
        $this->assertSame(0, $this->breaker->getFailures($host));
        $this->breaker->guard($host); // no throw
    }

    public function test_exception_carries_host_and_retry_after(): void
    {
        $host = 'open.example.com';
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $this->breaker->recordFailure($host);
        }

        try {
            $this->breaker->guard($host);
            $this->fail('Expected CircuitBreakerOpenException');
        } catch (CircuitBreakerOpenException $e) {
            $this->assertSame($host, $e->host);
            $this->assertGreaterThan(0, $e->retryAfterSeconds);
            $this->assertLessThanOrEqual(CircuitBreaker::RESET_AFTER, $e->retryAfterSeconds);
        }
    }
}
