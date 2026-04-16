<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * POST /api/v1/payments/create-intent
     * Creates a payment intent stub (Stripe parity).
     */
    public function createIntent(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|integer|min:50', // in cents
            'currency' => 'nullable|string|size:3',
            'booking_id' => 'nullable|uuid',
            'metadata' => 'nullable|array',
        ]);

        $intentId = 'pi_'.Str::random(24);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $intentId,
                'client_secret' => $intentId.'_secret_'.Str::random(24),
                'amount' => $request->amount,
                'currency' => strtolower($request->currency ?? 'eur'),
                'status' => 'requires_payment_method',
                'metadata' => $request->metadata ?? [],
                'created' => now()->timestamp,
            ],
            'error' => null,
            'meta' => null,
        ], 201);
    }

    /**
     * POST /api/v1/payments/confirm
     * Confirms a payment intent stub.
     * Returns proper status based on whether payment_method was provided.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'payment_method' => 'nullable|string',
        ]);

        // Without a payment method, the intent cannot succeed
        $status = $request->filled('payment_method') ? 'succeeded' : 'requires_payment_method';

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->payment_intent_id,
                'status' => $status,
                'amount_received' => $status === 'succeeded' ? null : 0,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/payments/{id}/status
     * Returns the status of a payment intent.
     * Stub returns 'requires_confirmation' since no real payment processor is configured.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => 'requires_confirmation',
                'amount' => null,
                'currency' => 'eur',
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * POST /api/v1/payments/webhook
     * Handles Stripe webhook events with signature verification.
     *
     * Fails closed: an unset STRIPE_WEBHOOK_SECRET rejects the request with 503
     * so unsigned payloads can never reach downstream handlers.
     */
    public function webhook(Request $request): JsonResponse
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if (! $webhookSecret) {
            Log::error('Stripe webhook rejected: STRIPE_WEBHOOK_SECRET not configured', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Webhook signature verification is not configured on this server',
            ], 503);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature', '');

        if (! $this->verifyStripeSignature($payload, $sigHeader, $webhookSecret)) {
            Log::warning('Stripe webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        Log::info('Stripe webhook received', [
            'type' => $request->input('type'),
            'data' => $request->input('data.object.id'),
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * Verify Stripe webhook signature (v1 scheme).
     */
    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        if (empty($sigHeader)) {
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

        // Reject timestamps older than 5 minutes to prevent replay attacks
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
