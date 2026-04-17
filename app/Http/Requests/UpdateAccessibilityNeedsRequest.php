<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccessibilityNeedsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'accessibility_needs' => 'required|string|in:none,wheelchair,reduced_mobility,visual,hearing',
        ];
    }
}
