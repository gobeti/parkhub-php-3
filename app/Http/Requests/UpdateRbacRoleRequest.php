<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRbacRoleRequest extends FormRequest
{
    private const VALID_PERMISSIONS = [
        'manage_users',
        'manage_lots',
        'manage_bookings',
        'view_reports',
        'manage_settings',
        'manage_plugins',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'permissions' => 'sometimes|required|array',
            'permissions.*' => 'string|in:'.implode(',', self::VALID_PERMISSIONS),
        ];
    }
}
