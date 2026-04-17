<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lot_id' => 'sometimes|uuid|exists:parking_lots,id',
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date',
            'reason' => 'sometimes|string|max:500',
            'affected_slots' => 'nullable|array',
        ];
    }
}
