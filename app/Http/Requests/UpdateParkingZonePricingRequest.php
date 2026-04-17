<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParkingZonePricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tier' => 'sometimes|required|string|in:economy,standard,premium,vip',
            'pricing_multiplier' => 'nullable|numeric|min:0.1|max:10.0',
            'max_capacity' => 'nullable|integer|min:1',
        ];
    }
}
