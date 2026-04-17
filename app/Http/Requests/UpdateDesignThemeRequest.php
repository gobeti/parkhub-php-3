<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDesignThemeRequest extends FormRequest
{
    private const VALID_THEMES = ['classic', 'glass', 'bento', 'brutalist', 'neon', 'warm'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'design_theme' => ['required', 'string', 'in:'.implode(',', self::VALID_THEMES)],
        ];
    }
}
