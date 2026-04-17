<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,'.$userId,
            'phone' => 'sometimes|nullable|string|max:50',
            'department' => 'sometimes|nullable|string|max:255',
            // Password changes should go through /users/me/password (requires current_password)
        ];
    }
}
