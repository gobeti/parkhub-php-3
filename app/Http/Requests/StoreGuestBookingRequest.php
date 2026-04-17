<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'lot_id' => 'required|uuid',
            'slot_id' => 'nullable|uuid',
            'guest_name' => 'required|string',
            'end_time' => 'required|date',
        ];
    }
}
