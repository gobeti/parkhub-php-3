<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParkingLot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Enhanced PWA controller — dynamic manifest and offline data support.
 *
 * Endpoints:
 *   GET /api/v1/pwa/manifest      — dynamic manifest.json based on branding settings
 *   GET /api/v1/pwa/offline-data  — essential data for offline mode (next booking, lot info)
 */
class PWAController extends Controller
{
    /**
     * Generate dynamic PWA manifest based on branding/settings.
     */
    public function manifest(): JsonResponse
    {
        $appName = config('app.name', 'ParkHub');
        $themeColor = config('parkhub.theme_color', '#6366f1');

        $manifest = [
            'name' => $appName,
            'short_name' => $appName,
            'description' => 'Self-hosted parking management',
            'start_url' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'theme_color' => $themeColor,
            'background_color' => '#ffffff',
            'categories' => ['business', 'utilities'],
            'prefer_related_applications' => false,
            'icons' => [
                ['src' => '/icons/icon-72.png', 'sizes' => '72x72', 'type' => 'image/png'],
                ['src' => '/icons/icon-96.png', 'sizes' => '96x96', 'type' => 'image/png'],
                ['src' => '/icons/icon-128.png', 'sizes' => '128x128', 'type' => 'image/png'],
                ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'shortcuts' => [
                [
                    'name' => 'Book a Spot',
                    'short_name' => 'Book',
                    'url' => '/book',
                    'icons' => [['src' => '/icons/icon-96.png', 'sizes' => '96x96']],
                ],
                [
                    'name' => 'My Bookings',
                    'short_name' => 'Bookings',
                    'url' => '/bookings',
                    'icons' => [['src' => '/icons/icon-96.png', 'sizes' => '96x96']],
                ],
            ],
            'screenshots' => [],
            'scope' => '/',
            'lang' => 'en',
        ];

        return response()->json([
            'success' => true,
            'data' => $manifest,
        ]);
    }

    /**
     * Provide essential data for offline mode — next booking and lot info.
     */
    public function offlineData(Request $request): JsonResponse
    {
        $user = $request->user();
        $nextBooking = null;
        $lotInfo = [];

        if ($user) {
            // Get user's next upcoming booking
            $booking = $user->bookings()
                ->where('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->first();

            if ($booking) {
                $slot = $booking->slot;
                $lot = $slot?->lot;

                $nextBooking = [
                    'id' => $booking->id,
                    'lot_name' => $lot?->name ?? 'Unknown',
                    'slot_label' => $slot?->label ?? $slot?->name ?? 'N/A',
                    'date' => $booking->date,
                    'start_time' => $booking->start_time,
                    'end_time' => $booking->end_time,
                ];
            }

            // Basic lot info for offline display
            $lots = ParkingLot::select('id', 'name', 'address', 'total_slots')->take(10)->get();
            $lotInfo = $lots->map(fn ($lot) => [
                'id' => $lot->id,
                'name' => $lot->name,
                'address' => $lot->address,
                'total_slots' => $lot->total_slots,
            ])->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'next_booking' => $nextBooking,
                'lot_info' => $lotInfo,
                'cached_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
