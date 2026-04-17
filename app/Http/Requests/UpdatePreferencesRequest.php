<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'language' => 'sometimes|string|max:10',
            'theme' => 'sometimes|in:light,dark,system',
            'notifications_enabled' => 'sometimes|boolean',
            'email_notifications' => 'sometimes|boolean',
            'push_notifications' => 'sometimes|boolean',
            'show_plate_in_calendar' => 'sometimes|boolean',
            'default_lot_id' => 'sometimes|nullable|uuid',
            'locale' => 'sometimes|string|max:10',
            'timezone' => 'sometimes|string|max:64',
        ];
    }
}
