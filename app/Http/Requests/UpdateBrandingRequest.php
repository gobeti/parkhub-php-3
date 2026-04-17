<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'sometimes|string|max:255',
            'primary_color' => ['sometimes', 'string', 'max:7', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_url' => 'sometimes|nullable|string|max:2048',
            'use_case' => 'sometimes|string|in:company,residential,shared,rental,personal',
        ];
    }
}
