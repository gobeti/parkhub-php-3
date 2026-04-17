<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048', 'regex:/^https?:\/\//'],
            'events' => 'nullable|array',
            'secret' => 'nullable|string|max:255',
            'active' => 'nullable|boolean',
        ];
    }
}
