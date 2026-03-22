<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferencesController extends Controller
{
    private array $defaults = [
        'email_booking_confirm' => true,
        'email_reminder' => true,
        'email_swap' => true,
        'push_enabled' => true,
        'quiet_hours_start' => null,
        'quiet_hours_end' => null,
    ];

    public function show(Request $request): JsonResponse
    {
        $prefs = $request->user()->notification_preferences ?? $this->defaults;

        return response()->json(array_merge($this->defaults, $prefs));
    }

    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'email_booking_confirm' => 'sometimes|boolean',
            'email_reminder' => 'sometimes|boolean',
            'email_swap' => 'sometimes|boolean',
            'push_enabled' => 'sometimes|boolean',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
        ]);

        $current = $request->user()->notification_preferences ?? $this->defaults;
        $updated = array_merge($current, $request->only([
            'email_booking_confirm', 'email_reminder', 'email_swap',
            'push_enabled', 'quiet_hours_start', 'quiet_hours_end',
        ]));

        $request->user()->update(['notification_preferences' => $updated]);

        return response()->json($updated);
    }
}
