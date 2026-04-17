<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InitSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Setup flow runs without an authenticated user (first-run bootstrap).
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'required|string',
            'admin_username' => 'required|string|min:3',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
            'admin_name' => 'required|string',
            'use_case' => 'nullable|string',
            'create_sample_data' => 'nullable|boolean',
        ];
    }
}
