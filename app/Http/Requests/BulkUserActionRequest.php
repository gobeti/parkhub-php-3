<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkUserActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|string|in:activate,deactivate,change_role,delete',
            'user_ids' => 'required|array|min:1|max:100',
            'user_ids.*' => 'uuid|exists:users,id',
            'role' => 'required_if:action,change_role|nullable|string|in:user,admin,premium',
        ];
    }
}
