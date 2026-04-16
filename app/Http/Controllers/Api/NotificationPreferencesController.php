<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationPreferencesController extends Controller
{
    private array $defaults = [
        'email_booking_confirm' => true,
        'email_reminder' => true,
        'email_swap' => true,
        'push_enabled' => true,
        'sms_enabled' => false,
        'whatsapp_enabled' => false,
        'phone_number' => null,
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
            'sms_enabled' => 'sometimes|boolean',
            'whatsapp_enabled' => 'sometimes|boolean',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
        ]);

        $current = $request->user()->notification_preferences ?? $this->defaults;
        $updated = array_merge($current, $request->only([
            'email_booking_confirm', 'email_reminder', 'email_swap',
            'push_enabled', 'sms_enabled', 'whatsapp_enabled', 'phone_number',
            'quiet_hours_start', 'quiet_hours_end',
        ]));

        // Stub dispatcher: log SMS/WhatsApp intent (no actual delivery)
        if (! empty($updated['sms_enabled']) && ! empty($updated['phone_number'])) {
            Log::info('Notification channel stub: would send SMS to '.$updated['phone_number']);
        }
        if (! empty($updated['whatsapp_enabled']) && ! empty($updated['phone_number'])) {
            Log::info('Notification channel stub: would send WhatsApp to '.$updated['phone_number']);
        }

        $request->user()->update(['notification_preferences' => $updated]);

        return response()->json($updated);
    }
}
