<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmailSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'smtp_host' => 'sometimes|string|max:255',
            'smtp_port' => 'sometimes|integer|between:1,65535',
            'smtp_user' => 'sometimes|string|max:255',
            'smtp_password' => 'sometimes|string|max:255',
            'smtp_from' => 'sometimes|email|max:255',
            'smtp_enabled' => 'sometimes|boolean',
        ];
    }
}
