<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadVehiclePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'photo' => 'required_without:photo_base64|image|mimes:jpeg,png,gif,webp|max:5120',
            'photo_base64' => 'required_without:photo|string|max:8388608', // 8 MB base64 cap
        ];
    }
}
