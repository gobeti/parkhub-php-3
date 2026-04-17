<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceWindowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lot_id' => 'required|uuid|exists:parking_lots,id',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'reason' => 'required|string|max:500',
            'affected_slots' => 'nullable|array',
        ];
    }
}
