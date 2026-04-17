<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSSOProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'display_name' => 'required|string|max:255',
            'entity_id' => 'required|string|max:1024',
            'sso_url' => 'required|url|max:2048',
            'certificate' => 'required|string',
            'metadata_url' => 'nullable|string|max:2048',
            'enabled' => 'boolean',
        ];
    }
}
