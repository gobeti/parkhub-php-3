<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SharingController extends Controller
{
    /**
     * In-memory share link store (production would use DB).
     * Keyed by booking ID for simplicity.
     */
    private static array $shares = [];

    /**
     * POST /bookings/{id}/share — generate a shareable link for a booking.
     */
    public function createShare(Request $request, string $id): JsonResponse
    {
        $booking = Booking::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'error' => 'BOOKING_NOT_FOUND',
                'message' => 'Booking not found or not owned by you',
            ], 404);
        }

        $expiresInHours = $request->input('expires_in_hours', 168);

        if (! is_numeric($expiresInHours) || (int) $expiresInHours < 0) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_EXPIRY',
                'message' => 'expires_in_hours must be a non-negative number',
            ], 422);
        }

        $code = Str::random(24);
        $now = now();
        $expiresAt = (int) $expiresInHours > 0
            ? $now->copy()->addHours((int) $expiresInHours)->toIso8601String()
            : null;

        $shareLink = [
            'id' => 'share-'.Str::uuid(),
            'booking_id' => $id,
            'code' => $code,
            'url' => "/shared/{$code}",
            'status' => 'active',
            'message' => $request->input('message'),
            'created_at' => $now->toIso8601String(),
            'expires_at' => $expiresAt,
            'view_count' => 0,
        ];

        self::$shares[$id] = $shareLink;

        return response()->json([
            'success' => true,
            'data' => $shareLink,
        ], 201);
    }

    /**
     * GET /shared/{code} — public view (no auth required).
     */
    public function viewShare(string $code): JsonResponse
    {
        // Search all shares for the code
        $share = collect(self::$shares)->firstWhere('code', $code);

        if (! $share) {
            return response()->json([
                'success' => false,
                'error' => 'SHARE_NOT_FOUND',
                'message' => 'Share link not found or has been revoked',
            ], 404);
        }

        if ($share['status'] === 'revoked') {
            return response()->json([
                'success' => false,
                'error' => 'SHARE_REVOKED',
                'message' => 'This share link has been revoked',
            ], 410);
        }

        if ($share['expires_at'] && now()->gt($share['expires_at'])) {
            return response()->json([
                'success' => false,
                'error' => 'SHARE_EXPIRED',
                'message' => 'This share link has expired',
            ], 410);
        }

        // Increment view count
        if (isset(self::$shares[$share['booking_id']])) {
            self::$shares[$share['booking_id']]['view_count']++;
            $share = self::$shares[$share['booking_id']];
        }

        $booking = Booking::find($share['booking_id']);

        return response()->json([
            'success' => true,
            'data' => [
                'share' => $share,
                'booking' => $booking ? [
                    'id' => $booking->id,
                    'lot_name' => $booking->lot?->name ?? 'Unknown',
                    'slot_label' => $booking->slot?->label ?? 'Unknown',
                    'date' => $booking->date,
                    'start_time' => $booking->start_time ?? null,
                    'end_time' => $booking->end_time ?? null,
                ] : null,
            ],
        ]);
    }

    /**
     * POST /bookings/{id}/invite — invite a guest via email.
     */
    public function inviteGuest(Request $request, string $id): JsonResponse
    {
        $booking = Booking::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'error' => 'BOOKING_NOT_FOUND',
                'message' => 'Booking not found or not owned by you',
            ], 404);
        }

        $email = $request->input('email');
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'success' => false,
                'error' => 'INVALID_EMAIL',
                'message' => 'A valid email address is required',
            ], 422);
        }

        $code = Str::random(24);
        $shareUrl = "/shared/{$code}";

        // In production, this would send an actual email
        $invite = [
            'invite_id' => 'invite-'.Str::uuid(),
            'booking_id' => $id,
            'email' => $email,
            'sent_at' => now()->toIso8601String(),
            'share_url' => $shareUrl,
            'message' => $request->input('message'),
        ];

        return response()->json([
            'success' => true,
            'data' => $invite,
        ], 201);
    }

    /**
     * DELETE /bookings/{id}/share — revoke the share link.
     */
    public function revokeShare(Request $request, string $id): JsonResponse
    {
        $booking = Booking::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $booking) {
            return response()->json([
                'success' => false,
                'error' => 'BOOKING_NOT_FOUND',
                'message' => 'Booking not found or not owned by you',
            ], 404);
        }

        if (isset(self::$shares[$id])) {
            self::$shares[$id]['status'] = 'revoked';
        }

        return response()->json([
            'success' => true,
            'data' => [
                'booking_id' => $id,
                'revoked' => true,
            ],
        ]);
    }
}
