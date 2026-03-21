<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$userId,
            'role' => 'sometimes|in:user,admin,superadmin',
            'is_active' => 'sometimes|boolean',
            'department' => 'sometimes|nullable|string|max:255',
            'password' => 'sometimes|string|min:8',
        ];
    }
}
