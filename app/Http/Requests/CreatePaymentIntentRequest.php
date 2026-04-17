<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePaymentIntentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|integer|min:50', // in cents
            'currency' => 'nullable|string|size:3',
            'booking_id' => 'nullable|uuid',
            'metadata' => 'nullable|array',
        ];
    }
}
