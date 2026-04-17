<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvChargerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lot_id' => 'required|uuid|exists:parking_lots,id',
            'label' => 'required|string|max:100',
            'connector_type' => 'required|in:type2,ccs,chademo,tesla',
            'power_kw' => 'required|numeric|min:1|max:350',
            'location_hint' => 'nullable|string|max:255',
        ];
    }
}
