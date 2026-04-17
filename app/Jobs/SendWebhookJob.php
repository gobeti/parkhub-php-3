<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\CircuitBreakerOpenException;
use App\Models\Webhook;
use App\Services\CircuitBreaker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        private string $webhookId,
        private string $event,
        private array $payload,
    ) {}

    public function handle(CircuitBreaker $breaker): void
    {
        $webhook = Webhook::find($this->webhookId);
        if (! $webhook || ! $webhook->active) {
            return;
        }

        $host = $breaker->hostFromUrl($webhook->url);

        // Per-host breaker check — reject immediately if we know the host is down.
        // On trip, fail the job (no retry) until the breaker closes again.
        try {
            $breaker->guard($host);
        } catch (CircuitBreakerOpenException $e) {
            Log::warning('Webhook delivery skipped: circuit breaker open', [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
                'host' => $e->host,
                'retry_after' => $e->retryAfterSeconds,
            ]);
            // fail() → queue marks job as failed, no automatic retry.
            $this->fail($e);

            return;
        }

        $body = json_encode([
            'event' => $this->event,
            'payload' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ]);

        $headers = ['Content-Type' => 'application/json'];
        if ($webhook->secret) {
            $sig = hash_hmac('sha256', $body, $webhook->secret);
            $headers['X-Parkhub-Signature'] = 'sha256='.$sig;
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->connectTimeout(5)
                ->post($webhook->url, json_decode($body, true));
        } catch (\Throwable $e) {
            // Connection refused / DNS / TLS / timeout — all count as breaker failures.
            $breaker->recordFailure($host);
            throw $e;
        }

        if (! $response->successful()) {
            $breaker->recordFailure($host);
            Log::warning('Webhook delivery failed', [
                'webhook_id' => $this->webhookId,
                'event' => $this->event,
                'status' => $response->status(),
                'attempt' => $this->attempts(),
            ]);
            // Throw exception to trigger retry (up to $tries attempts).
            // The failed() method handles permanent failure after all retries are exhausted.
            throw new \RuntimeException("Webhook delivery failed with HTTP {$response->status()}");
        }

        $breaker->recordSuccess($host);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Webhook permanently failed', [
            'webhook_id' => $this->webhookId,
            'event' => $this->event,
            'error' => $e->getMessage(),
        ]);
    }
}
