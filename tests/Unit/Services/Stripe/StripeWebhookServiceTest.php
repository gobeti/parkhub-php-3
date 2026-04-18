<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Stripe;

use App\Models\User;
use App\Services\Stripe\StripeWebhookOutcome;
use App\Services\Stripe\StripeWebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StripeWebhookServiceTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'whsec_test_abc123';

    private function buildSignatureHeader(string $payload, int $timestamp, string $secret = self::SECRET): string
    {
        $sig = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return "t={$timestamp},v1={$sig}";
    }

    public function test_happy_path_grants_credits_on_checkout_session_completed(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);

        $user = User::factory()->create(['credits_balance' => 0]);

        DB::table('stripe_payments')->insert([
            'id' => 'cs_test_grant_001',
            'user_id' => $user->id,
            'amount' => 1000,
            'credits' => 5,
            'currency' => 'eur',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = json_encode([
            'id' => 'evt_happy_001',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_grant_001']],
        ]);

        $header = $this->buildSignatureHeader($payload, time());

        $result = app(StripeWebhookService::class)->process($payload, $header, '198.51.100.1');

        $this->assertTrue($result->isOk());
        $this->assertSame(StripeWebhookOutcome::Received, $result->outcome);
        $this->assertSame(200, $result->status);
        $this->assertSame('checkout.session.completed', $result->eventType);
        $this->assertDatabaseHas('stripe_payments', [
            'id' => 'cs_test_grant_001',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('stripe_events', [
            'event_id' => 'evt_happy_001',
            'type' => 'checkout.session.completed',
        ]);
        $this->assertSame(5, (int) $user->fresh()->credits_balance);
    }

    public function test_rejects_invalid_signature(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);

        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'x']]]);
        $header = 't='.time().',v1=not-a-real-signature';

        $result = app(StripeWebhookService::class)->process($payload, $header, '198.51.100.2');

        $this->assertFalse($result->isOk());
        $this->assertSame(StripeWebhookOutcome::InvalidSignature, $result->outcome);
        $this->assertSame(403, $result->status);
    }

    public function test_fails_closed_when_webhook_secret_is_not_configured(): void
    {
        config(['services.stripe.webhook_secret' => null]);

        $payload = json_encode(['type' => 'checkout.session.completed']);
        $result = app(StripeWebhookService::class)->process($payload, 't=0,v1=x', '198.51.100.3');

        $this->assertFalse($result->isOk());
        $this->assertSame(StripeWebhookOutcome::NotConfigured, $result->outcome);
        $this->assertSame(503, $result->status);
    }

    public function test_rejects_replayed_timestamp_outside_tolerance(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);

        $payload = json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'y']]]);
        // 10 minutes old -> beyond the 5-minute tolerance window
        $oldTimestamp = time() - 600;
        $header = $this->buildSignatureHeader($payload, $oldTimestamp);

        $result = app(StripeWebhookService::class)->process($payload, $header, '198.51.100.4');

        $this->assertFalse($result->isOk());
        $this->assertSame(StripeWebhookOutcome::InvalidSignature, $result->outcome);
        $this->assertSame(403, $result->status);
    }

    public function test_does_not_double_credit_on_replayed_completion(): void
    {
        config(['services.stripe.webhook_secret' => self::SECRET]);

        $user = User::factory()->create(['credits_balance' => 0]);

        DB::table('stripe_payments')->insert([
            'id' => 'cs_test_replay_001',
            'user_id' => $user->id,
            'amount' => 1000,
            'credits' => 3,
            'currency' => 'eur',
            'status' => 'completed',
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = json_encode([
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => 'cs_test_replay_001']],
        ]);
        $header = $this->buildSignatureHeader($payload, time());

        $result = app(StripeWebhookService::class)->process($payload, $header, '198.51.100.5');

        $this->assertTrue($result->isOk());
        $this->assertSame(0, (int) $user->fresh()->credits_balance);
    }
}
