<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApiKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string|max:100',
        ];
    }
}
