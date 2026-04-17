<?php

declare(strict_types=1);

namespace App\Services\Stripe;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Owns the Stripe webhook verification + event dispatch extracted from
 * StripeController::webhook() (T-1742, pass 2).
 *
 * Pure extraction — HMAC verification (v1 scheme, 5-minute timestamp
 * tolerance), the fail-closed behaviour when STRIPE_WEBHOOK_SECRET is
 * missing, the audit-log messages and the checkout.session.completed
 * credit-grant flow all match the previous inline controller
 * implementation. Controllers stay responsible for HTTP shaping via the
 * returned StripeWebhookResult.
 */
final class StripeWebhookService
{
    /**
     * Verify the request signature and dispatch the relevant event handler.
     *
     * Fails closed: when STRIPE_WEBHOOK_SECRET is not configured the
     * caller is expected to return 503 so unsigned payloads can never
     * grant credits.
     */
    public function process(string $payload, string $signatureHeader, ?string $ip = null): StripeWebhookResult
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if (! $webhookSecret) {
            Log::error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET not configured', [
                'ip' => $ip,
            ]);

            return StripeWebhookResult::notConfigured();
        }

        if (! $this->verifySignature($payload, $signatureHeader, (string) $webhookSecret)) {
            Log::warning('Stripe webhook signature verification failed', ['ip' => $ip]);

            return StripeWebhookResult::invalidSignature();
        }

        $event = json_decode($payload, true);
        $type = is_array($event) ? ($event['type'] ?? 'unknown') : 'unknown';
        $object = is_array($event) ? ($event['data']['object'] ?? []) : [];

        Log::info('Stripe webhook received', [
            'type' => $type,
            'id' => is_array($object) ? ($object['id'] ?? null) : null,
        ]);

        if ($type === 'checkout.session.completed' && is_array($object)) {
            $this->handleCheckoutSessionCompleted($object);
        }

        return StripeWebhookResult::received($type);
    }

    /**
     * Stripe signature header parser + HMAC comparison (v1 scheme).
     *
     * The header is a comma-separated list of "k=v" pairs, with at least
     * `t` (unix timestamp) and `v1` (HMAC-SHA256 of `{t}.{payload}`
     * signed with the webhook secret). We reject requests older than 5
     * minutes to prevent replay, and use hash_equals to compare in
     * constant time.
     */
    private function verifySignature(string $payload, string $sigHeader, string $secret): bool
    {
        if ($sigHeader === '') {
            return false;
        }

        $parts = collect(explode(',', $sigHeader))->mapWithKeys(function ($part) {
            $pair = explode('=', trim($part), 2);

            return count($pair) === 2 ? [$pair[0] => $pair[1]] : [];
        });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (! $timestamp || ! $signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expected, (string) $signature);
    }

    /**
     * Mark the recorded checkout session as completed and credit the user.
     *
     * Only transitions `pending` → `completed` once, preventing
     * double-credit on replayed or duplicate events.
     */
    private function handleCheckoutSessionCompleted(array $object): void
    {
        $sessionId = $object['id'] ?? null;

        if (! $sessionId) {
            return;
        }

        $payment = DB::table('stripe_payments')->where('id', $sessionId)->first();

        if (! $payment || $payment->status !== 'pending') {
            return;
        }

        DB::table('stripe_payments')
            ->where('id', $sessionId)
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('users')
            ->where('id', $payment->user_id)
            ->increment('credits_balance', $payment->credits);
    }
}
