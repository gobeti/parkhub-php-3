<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateStripeCheckoutRequest;
use App\Services\Stripe\StripeWebhookOutcome;
use App\Services\Stripe\StripeWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\Stripe;

class StripeController extends Controller
{
    /**
     * POST /api/v1/payments/create-checkout
     *
     * Create a Stripe Checkout Session for purchasing credits.
     * Falls back to a stub if Stripe SDK is not available.
     */
    public function createCheckout(CreateStripeCheckoutRequest $request): JsonResponse
    {
        $credits = $request->integer('credits');
        $pricePerCredit = $request->float('price_per_credit', 1.00);
        $amount = (int) round($credits * $pricePerCredit * 100); // cents
        $currency = config('services.stripe.currency', 'eur');

        $secretKey = config('services.stripe.secret');

        if (
            $secretKey
            && class_exists('\Stripe\Stripe')
            && class_exists('\Stripe\Checkout\Session')
        ) {
            Stripe::setApiKey($secretKey);

            try {
                /** @var class-string $sessionClass */
                $sessionClass = '\Stripe\Checkout\Session';
                $session = $sessionClass::create([
                    'payment_method_types' => ['card'],
                    'mode' => 'payment',
                    'line_items' => [[
                        'price_data' => [
                            'currency' => $currency,
                            'product_data' => [
                                'name' => "ParkHub Credits ({$credits}x)",
                            ],
                            'unit_amount' => $amount,
                        ],
                        'quantity' => 1,
                    ]],
                    'metadata' => [
                        'user_id' => $request->user()->id,
                        'credits' => $credits,
                    ],
                    'success_url' => url('/credits?session_id={CHECKOUT_SESSION_ID}'),
                    'cancel_url' => url('/credits'),
                ]);

                // Record the pending payment
                DB::table('stripe_payments')->insert([
                    'id' => $session->id,
                    'user_id' => $request->user()->id,
                    'amount' => $amount,
                    'credits' => $credits,
                    'currency' => $currency,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => $session->id,
                        'checkout_url' => $session->url,
                        'amount' => $amount,
                        'credits' => $credits,
                        'currency' => $currency,
                    ],
                    'error' => null,
                    'meta' => null,
                ]);
            } catch (\Exception $e) {
                Log::error('Stripe checkout creation failed', ['error' => $e->getMessage()]);

                return response()->json([
                    'success' => false,
                    'data' => null,
                    'error' => ['code' => 'STRIPE_ERROR', 'message' => 'Failed to create checkout session'],
                    'meta' => null,
                ], 500);
            }
        }

        // Stub mode — no real Stripe
        $sessionId = 'cs_stub_'.Str::random(24);

        DB::table('stripe_payments')->insert([
            'id' => $sessionId,
            'user_id' => $request->user()->id,
            'amount' => $amount,
            'credits' => $credits,
            'currency' => $currency,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $sessionId,
                'checkout_url' => url("/credits?session_id={$sessionId}"),
                'amount' => $amount,
                'credits' => $credits,
                'currency' => $currency,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * POST /api/v1/payments/webhook
     *
     * Handle Stripe webhook events. Fails closed: a missing webhook secret
     * rejects the request with 503 so unsigned payloads can never grant credits.
     */
    public function webhook(Request $request, StripeWebhookService $service): JsonResponse
    {
        $sigHeader = (string) $request->header('Stripe-Signature', '');

        $result = $service->process($request->getContent(), $sigHeader, $request->ip());

        return match ($result->outcome) {
            StripeWebhookOutcome::Received => response()->json(['received' => true]),
            StripeWebhookOutcome::AlreadyProcessed => response()->json([
                'received' => true,
                'already_processed' => true,
            ]),
            StripeWebhookOutcome::InvalidSignature => response()->json(['error' => 'Invalid signature'], 403),
            StripeWebhookOutcome::NotConfigured => response()->json([
                'error' => 'Webhook signature verification is not configured on this server',
            ], 503),
        };
    }

    /**
     * GET /api/v1/payments/history
     *
     * List payment history for the authenticated user.
     */
    public function history(Request $request): JsonResponse
    {
        $payments = DB::table('stripe_payments')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'credits' => $p->credits,
                'currency' => $p->currency,
                'status' => $p->status,
                'created_at' => $p->created_at,
                'completed_at' => $p->completed_at ?? null,
            ]);

        return response()->json([
            'success' => true,
            'data' => $payments,
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/payments/config/status
     *
     * Check whether Stripe is configured (has API keys set).
     */
    public function configStatus(): JsonResponse
    {
        $configured = ! empty(config('services.stripe.secret'))
            && ! empty(config('services.stripe.key'));

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $configured,
                'publishable_key' => $configured ? config('services.stripe.key') : null,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }
}
