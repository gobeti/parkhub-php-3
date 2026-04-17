<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_24h' => 'sometimes|boolean',
            'monday' => 'sometimes|array',
            'monday.open' => 'sometimes|date_format:H:i',
            'monday.close' => 'sometimes|date_format:H:i',
            'monday.closed' => 'sometimes|boolean',
            'tuesday' => 'sometimes|array',
            'tuesday.open' => 'sometimes|date_format:H:i',
            'tuesday.close' => 'sometimes|date_format:H:i',
            'tuesday.closed' => 'sometimes|boolean',
            'wednesday' => 'sometimes|array',
            'wednesday.open' => 'sometimes|date_format:H:i',
            'wednesday.close' => 'sometimes|date_format:H:i',
            'wednesday.closed' => 'sometimes|boolean',
            'thursday' => 'sometimes|array',
            'thursday.open' => 'sometimes|date_format:H:i',
            'thursday.close' => 'sometimes|date_format:H:i',
            'thursday.closed' => 'sometimes|boolean',
            'friday' => 'sometimes|array',
            'friday.open' => 'sometimes|date_format:H:i',
            'friday.close' => 'sometimes|date_format:H:i',
            'friday.closed' => 'sometimes|boolean',
            'saturday' => 'sometimes|array',
            'saturday.open' => 'sometimes|date_format:H:i',
            'saturday.close' => 'sometimes|date_format:H:i',
            'saturday.closed' => 'sometimes|boolean',
            'sunday' => 'sometimes|array',
            'sunday.open' => 'sometimes|date_format:H:i',
            'sunday.close' => 'sometimes|date_format:H:i',
            'sunday.closed' => 'sometimes|boolean',
        ];
    }
}
