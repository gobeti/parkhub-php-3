<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PushController extends Controller
{
    /**
     * POST /api/v1/push/subscribe
     *
     * Store a Web Push subscription for the authenticated user.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|url|max:2048',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
        ]);

        $user = $request->user();

        // Upsert: replace existing subscription for same endpoint
        $existing = DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->where('endpoint', $request->input('endpoint'))
            ->first();

        if ($existing) {
            DB::table('push_subscriptions')
                ->where('id', $existing->id)
                ->update([
                    'p256dh' => $request->input('keys.p256dh'),
                    'auth' => $request->input('keys.auth'),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('push_subscriptions')->insert([
                'id' => Str::uuid()->toString(),
                'user_id' => $user->id,
                'endpoint' => $request->input('endpoint'),
                'p256dh' => $request->input('keys.p256dh'),
                'auth' => $request->input('keys.auth'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Subscription stored'],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * DELETE /api/v1/push/unsubscribe
     *
     * Remove all push subscriptions for the authenticated user.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::table('push_subscriptions')
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'success' => true,
            'data' => ['message' => 'Unsubscribed'],
            'error' => null,
            'meta' => null,
        ]);
    }

    /**
     * GET /api/v1/push/vapid-key
     *
     * Return the VAPID public key so the frontend can subscribe.
     */
    public function vapidKey(): JsonResponse
    {
        $publicKey = config('services.webpush.vapid_public_key');

        if (! $publicKey) {
            return response()->json([
                'success' => false,
                'data' => null,
                'error' => ['code' => 'NOT_CONFIGURED', 'message' => 'Web Push not configured'],
                'meta' => null,
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data' => ['public_key' => $publicKey],
            'error' => null,
            'meta' => null,
        ]);
    }
}
