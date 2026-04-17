<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadBrandingLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // SVG intentionally excluded: SVGs can carry inline <script> or
            // javascript: refs and a served logo becomes a stored-XSS vector.
            // Matches the parkhub-rust side which magic-byte-checks only
            // JPEG / PNG / GIF / WebP. (T-1736)
            'logo' => 'required|image|mimes:jpeg,png,gif,webp|max:2048',
        ];
    }
}
