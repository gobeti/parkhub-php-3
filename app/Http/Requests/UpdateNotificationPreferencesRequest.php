<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'email_booking_confirm' => 'sometimes|boolean',
            'email_reminder' => 'sometimes|boolean',
            'email_swap' => 'sometimes|boolean',
            'push_enabled' => 'sometimes|boolean',
            'sms_enabled' => 'sometimes|boolean',
            'whatsapp_enabled' => 'sometimes|boolean',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'quiet_hours_start' => 'sometimes|nullable|date_format:H:i',
            'quiet_hours_end' => 'sometimes|nullable|date_format:H:i',
        ];
    }
}
