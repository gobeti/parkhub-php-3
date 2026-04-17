<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexBookingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'from_date' => 'sometimes|date',
            'to_date' => 'sometimes|date',
            'status' => 'sometimes|string|in:active,confirmed,cancelled,completed,expired',
        ];
    }
}
