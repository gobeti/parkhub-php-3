<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'message' => 'sometimes|string',
            'severity' => 'sometimes|in:info,warning,error,success',
            'active' => 'sometimes|boolean',
            'expires_at' => 'sometimes|nullable|date',
        ];
    }
}
