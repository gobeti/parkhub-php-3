<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $intentId = 'pi_' . Str::random(24);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $intentId,
                'client_secret' => $intentId . '_secret_' . Str::random(24),
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
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'payment_method' => 'nullable|string',
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $request->payment_intent_id,
                'status' => 'succeeded',
                'amount_received' => null,
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/payments/{id}/status
     * Returns the status of a payment intent.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $id,
                'status' => 'succeeded',
                'amount' => null,
                'currency' => 'eur',
            ],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * POST /api/v1/payments/webhook
     * Handles Stripe webhook events (stub - logs and returns 200).
     */
    public function webhook(Request $request): JsonResponse
    {
        // In production: verify Stripe-Signature header
        // $payload = $request->getContent();
        // $sigHeader = $request->header('Stripe-Signature');

        \Illuminate\Support\Facades\Log::info('Stripe webhook received', [
            'type' => $request->input('type'),
            'data' => $request->input('data.object.id'),
        ]);

        return response()->json(['received' => true]);
    }
}
