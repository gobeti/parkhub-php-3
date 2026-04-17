<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Webhook;

use App\Models\User;
use App\Services\CircuitBreaker;
use App\Services\Webhook\WebhookDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebhookDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): WebhookDispatchService
    {
        return app(WebhookDispatchService::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up persistent webhook storage between tests.
        $path = storage_path('app/webhooks_v2.json');
        if (file_exists($path)) {
            unlink($path);
        }
        foreach (glob(storage_path('app/webhook_deliveries_*.json')) ?: [] as $f) {
            unlink($f);
        }
    }

    public function test_create_persists_webhook_with_signed_id_and_secret(): void
    {
        $webhook = $this->service()->create([
            'url' => 'https://example.com/hook',
            'events' => ['booking.created'],
            'description' => 'Test hook',
        ]);

        $this->assertStringStartsWith('wh-', $webhook['id']);
        $this->assertStringStartsWith('whsec_', $webhook['secret']);
        $this->assertSame('https://example.com/hook', $webhook['url']);
        $this->assertTrue($webhook['active']);
        $this->assertSame(['booking.created'], $webhook['events']);

        $reloaded = $this->service()->find($webhook['id']);
        $this->assertNotNull($reloaded);
        $this->assertSame($webhook['id'], $reloaded['id']);
    }

    public function test_update_returns_null_for_unknown_id_and_merges_partial_payload(): void
    {
        $this->assertNull($this->service()->update('wh-missing', ['url' => 'https://x']));

        $created = $this->service()->create([
            'url' => 'https://example.com/hook',
            'events' => ['booking.created'],
            'description' => 'Orig',
        ]);

        // Partial payload: only events — url/description must stay.
        $updated = $this->service()->update($created['id'], [
            'events' => ['booking.cancelled'],
        ]);

        $this->assertNotNull($updated);
        $this->assertSame('https://example.com/hook', $updated['url']);
        $this->assertSame(['booking.cancelled'], $updated['events']);
        $this->assertSame('Orig', $updated['description']);
    }

    public function test_delete_removes_webhook_and_purges_delivery_log(): void
    {
        $created = $this->service()->create([
            'url' => 'https://example.com/hook',
            'events' => ['booking.created'],
        ]);

        // Seed a delivery log file to confirm it is cleaned up.
        $deliveryPath = storage_path("app/webhook_deliveries_{$created['id']}.json");
        file_put_contents($deliveryPath, '[{"id":"del-1"}]');
        $this->assertFileExists($deliveryPath);

        $this->assertTrue($this->service()->delete($created['id']));
        $this->assertNull($this->service()->find($created['id']));
        $this->assertFileDoesNotExist($deliveryPath);

        // Second delete on same ID is a no-op.
        $this->assertFalse($this->service()->delete($created['id']));
    }

    public function test_sign_payload_produces_hmac_sha256_that_matches_hmac_header(): void
    {
        $body = json_encode(['event' => 'test.ping', 'data' => ['x' => 1]]);
        $secret = 'whsec_ABC';

        $signature = $this->service()->signPayload((string) $body, $secret);
        $expected = hash_hmac('sha256', (string) $body, $secret);

        $this->assertSame($expected, $signature);
        // sha256 hex digest is 64 chars.
        $this->assertSame(64, strlen($signature));
    }

    public function test_dispatch_short_circuits_on_open_breaker_and_records_failed_delivery(): void
    {
        // Force the breaker OPEN for the target host before dispatch.
        /** @var CircuitBreaker $breaker */
        $breaker = app(CircuitBreaker::class);
        $host = $breaker->hostFromUrl('https://hooks.example.com/endpoint');
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $breaker->recordFailure($host);
        }
        $this->assertSame(CircuitBreaker::STATE_OPEN, $breaker->getState($host));

        $webhook = $this->service()->create([
            'url' => 'https://hooks.example.com/endpoint',
            'events' => ['booking.created'],
        ]);

        $result = $this->service()->dispatch($webhook, 'booking.created', ['id' => 'bk-1']);

        $this->assertFalse($result['success']);
        $this->assertNull($result['status_code']);
        $this->assertNotNull($result['error']);

        // Delivery log must reflect the rejected attempt.
        $deliveries = $this->service()->deliveries($webhook['id']);
        $this->assertCount(1, $deliveries);
        $this->assertFalse($deliveries[0]['success']);
        $this->assertNull($deliveries[0]['status_code']);
        $this->assertSame('booking.created', $deliveries[0]['event_type']);
    }

    public function test_replay_delegates_to_dispatch_and_emits_audit_log(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);

        // Breaker OPEN to keep dispatch()'s outbound request from firing
        // — we only care about the audit trail, not transport success.
        /** @var CircuitBreaker $breaker */
        $breaker = app(CircuitBreaker::class);
        $host = $breaker->hostFromUrl('https://replay.example.com/endpoint');
        for ($i = 0; $i < CircuitBreaker::FAILURE_THRESHOLD; $i++) {
            $breaker->recordFailure($host);
        }

        $webhook = $this->service()->create([
            'url' => 'https://replay.example.com/endpoint',
            'events' => ['booking.created'],
        ]);

        $result = $this->service()->replay(
            $webhook,
            'booking.created',
            ['id' => 'bk-42'],
            $actor->id,
            $actor->username,
        );

        $this->assertFalse($result['success']);

        $audit = DB::table('audit_log')
            ->where('user_id', $actor->id)
            ->where('action', 'webhook_replayed')
            ->first();
        $this->assertNotNull($audit);
        $decoded = is_string($audit->details) ? json_decode($audit->details, true) : $audit->details;
        $this->assertSame($webhook['id'], $decoded['webhook_id']);
        $this->assertSame('booking.created', $decoded['event']);
        $this->assertFalse($decoded['success']);
    }
}
