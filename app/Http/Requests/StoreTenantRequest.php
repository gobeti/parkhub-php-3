<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'domain' => 'nullable|string|max:255',
            'branding' => 'nullable|array',
            'branding.primary_color' => 'nullable|string|max:7',
            'branding.logo_url' => 'nullable|string|max:500',
            'branding.company_name' => 'nullable|string|max:255',
        ];
    }
}
