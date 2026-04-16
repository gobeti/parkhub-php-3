<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'users' => 'required|array|max:500',
            'users.*.username' => 'required|string|min:3|max:50|alpha_dash',
            'users.*.email' => 'required|email|max:255',
            'users.*.name' => 'nullable|string|max:255',
            'users.*.role' => 'nullable|in:user,admin',
            'users.*.department' => 'nullable|string|max:255',
            'users.*.password' => 'nullable|string|min:8|max:128',
        ];
    }
}
