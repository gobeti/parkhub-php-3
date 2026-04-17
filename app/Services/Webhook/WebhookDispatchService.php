<?php

declare(strict_types=1);

namespace App\Services\Webhook;

use App\Exceptions\CircuitBreakerOpenException;
use App\Models\AuditLog;
use App\Services\CircuitBreaker;
use Illuminate\Support\Str;

/**
 * Owns the v2 webhook storage, HMAC signing, delivery dispatch and
 * replay flow extracted from WebhookV2Controller (T-1742, pass 5).
 *
 * Pure extraction — the JSON-file storage layout
 * (`storage/app/webhooks_v2.json` + per-webhook
 * `webhook_deliveries_{id}.json` ring buffer of the most recent 100
 * entries), the `whsec_` / `wh-` / `del-` ID prefixes, the HMAC-SHA256
 * signature header shape (`X-ParkHub-Signature: sha256=...`) and the
 * curl transport with 15s total + 5s connect timeouts all match the
 * previous inline controller implementation.
 *
 * Circuit-breaker integration: each delivery is guarded by the
 * per-host CircuitBreaker (T-1732). A delivery rejected by an OPEN
 * breaker is recorded with `success=false` and `status_code=null` so
 * the delivery log reflects the attempt.
 *
 * Controllers remain responsible for FormRequest validation, HTTP
 * shaping and SSRF allowlisting (via ValidatesExternalUrls).
 */
final class WebhookDispatchService
{
    public function __construct(private readonly CircuitBreaker $breaker) {}

    /**
     * Return all configured v2 webhooks in storage order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->loadWebhooks();
    }

    /**
     * Look up a single webhook by ID, or null when none matches.
     *
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return collect($this->loadWebhooks())->firstWhere('id', $id);
    }

    /**
     * Persist a new webhook and return the stored row. Caller is
     * responsible for SSRF validation of `url` before calling.
     *
     * @param  array<string, mixed>  $payload  Validated by StoreWebhookV2Request.
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $webhook = [
            'id' => 'wh-'.Str::random(12),
            'url' => (string) $payload['url'],
            'secret' => 'whsec_'.Str::random(32),
            'events' => $payload['events'],
            'active' => (bool) ($payload['active'] ?? true),
            'description' => $payload['description'] ?? null,
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        $webhooks = $this->loadWebhooks();
        $webhooks[] = $webhook;
        $this->saveWebhooks($webhooks);

        return $webhook;
    }

    /**
     * Update an existing webhook by ID. Returns the updated row, or
     * null when no webhook with that ID exists.
     *
     * @param  array<string, mixed>  $payload  Validated by UpdateWebhookV2Request.
     * @return array<string, mixed>|null
     */
    public function update(string $id, array $payload): ?array
    {
        $webhooks = $this->loadWebhooks();
        $index = collect($webhooks)->search(fn ($w) => $w['id'] === $id);

        if ($index === false) {
            return null;
        }

        $webhook = $webhooks[$index];
        $webhook['url'] = (string) ($payload['url'] ?? $webhook['url']);
        $webhook['events'] = $payload['events'] ?? $webhook['events'];
        $webhook['description'] = $payload['description'] ?? $webhook['description'];
        $webhook['active'] = (bool) ($payload['active'] ?? $webhook['active']);
        $webhook['updated_at'] = now()->toIso8601String();

        $webhooks[$index] = $webhook;
        $this->saveWebhooks($webhooks);

        return $webhook;
    }

    /**
     * Delete a webhook by ID. Also purges its delivery log. Returns
     * false when no webhook with that ID exists.
     */
    public function delete(string $id): bool
    {
        $webhooks = $this->loadWebhooks();
        $filtered = collect($webhooks)->reject(fn ($w) => $w['id'] === $id)->values()->toArray();

        if (count($filtered) === count($webhooks)) {
            return false;
        }

        $this->saveWebhooks($filtered);
        $this->deleteDeliveries($id);

        return true;
    }

    /**
     * Dispatch a single event to a webhook target. Signs with HMAC-SHA256
     * against the stored secret, records a delivery log entry, and
     * consults the CircuitBreaker before the outbound request.
     *
     * @param  array<string, mixed>  $webhook  A row from list() / find().
     * @param  array<string, mixed>  $payload  JSON-serialisable body.
     * @return array{success: bool, status_code: int|null, error: string|null}
     */
    public function dispatch(array $webhook, string $event, array $payload): array
    {
        $body = json_encode([
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $payload,
        ]);

        if ($body === false) {
            $body = '{}';
        }

        $host = $this->breaker->hostFromUrl((string) $webhook['url']);
        $signature = $this->signPayload($body, (string) $webhook['secret']);

        $success = false;
        $statusCode = null;
        $error = null;

        try {
            $this->breaker->guard($host);

            [$statusCode, $error] = $this->sendRequest(
                (string) $webhook['url'],
                $body,
                $event,
                $signature,
            );

            $success = $statusCode !== null && $statusCode >= 200 && $statusCode < 300;

            if ($success) {
                $this->breaker->recordSuccess($host);
            } else {
                $this->breaker->recordFailure($host);
            }
        } catch (CircuitBreakerOpenException $e) {
            $error = $e->getMessage();
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $this->breaker->recordFailure($host);
        }

        $this->recordDelivery((string) $webhook['id'], [
            'id' => 'del-'.Str::random(12),
            'event_type' => $event,
            'status_code' => $statusCode,
            'success' => $success,
            'attempt' => 1,
            'error' => $error,
            'delivered_at' => now()->toIso8601String(),
        ]);

        return [
            'success' => $success,
            'status_code' => $statusCode,
            'error' => $error,
        ];
    }

    /**
     * Replay a previously delivered event against the same webhook.
     * Emits an AuditLog entry scoped to the actor so operators retain a
     * trail of manual re-sends.
     *
     * @param  array<string, mixed>  $webhook
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, status_code: int|null, error: string|null}
     */
    public function replay(array $webhook, string $event, array $payload, string $actorId, string $actorUsername): array
    {
        $result = $this->dispatch($webhook, $event, $payload);

        AuditLog::log([
            'user_id' => $actorId,
            'username' => $actorUsername,
            'action' => 'webhook_replayed',
            'details' => [
                'webhook_id' => $webhook['id'] ?? null,
                'event' => $event,
                'success' => $result['success'],
                'status_code' => $result['status_code'],
            ],
        ]);

        return $result;
    }

    /**
     * Return the stored delivery log for a webhook (most recent first,
     * capped at 100 entries). Empty array when none.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deliveries(string $webhookId): array
    {
        return $this->loadDeliveries($webhookId);
    }

    /**
     * HMAC-SHA256 sign a webhook payload with the stored secret.
     */
    public function signPayload(string $body, string $secret): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    /**
     * Perform the outbound HTTP request and return (status, error).
     *
     * @return array{0: int|null, 1: string|null}
     */
    private function sendRequest(string $url, string $body, string $event, string $signature): array
    {
        $ch = curl_init($url);

        if ($ch === false) {
            return [null, 'curl_init failed'];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-ParkHub-Signature: sha256='.$signature,
                'X-ParkHub-Event: '.$event,
                'X-ParkHub-Delivery: '.Str::uuid(),
                'User-Agent: ParkHub-Webhooks/2.0',
            ],
        ]);

        curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_errno($ch) ? curl_error($ch) : null;
        curl_close($ch);

        return [$statusCode ?: null, $error];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadWebhooks(): array
    {
        $path = storage_path('app/webhooks_v2.json');

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $webhooks
     */
    private function saveWebhooks(array $webhooks): void
    {
        $path = storage_path('app/webhooks_v2.json');
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $path,
            json_encode(array_values($webhooks), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadDeliveries(string $webhookId): array
    {
        $path = storage_path("app/webhook_deliveries_{$webhookId}.json");

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $delivery
     */
    private function recordDelivery(string $webhookId, array $delivery): void
    {
        $deliveries = $this->loadDeliveries($webhookId);
        array_unshift($deliveries, $delivery);
        // Keep last 100 deliveries
        $deliveries = array_slice($deliveries, 0, 100);

        $path = storage_path("app/webhook_deliveries_{$webhookId}.json");
        file_put_contents(
            $path,
            json_encode($deliveries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }

    private function deleteDeliveries(string $webhookId): void
    {
        $path = storage_path("app/webhook_deliveries_{$webhookId}.json");

        if (file_exists($path)) {
            unlink($path);
        }
    }
}
