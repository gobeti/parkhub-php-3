<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParkingSlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slot_number' => 'sometimes|string|max:20',
            'status' => 'sometimes|in:available,occupied,reserved,maintenance',
            'reserved_for_department' => 'sometimes|nullable|string|max:255',
            'zone_id' => 'sometimes|nullable|uuid|exists:zones,id',
        ];
    }
}
