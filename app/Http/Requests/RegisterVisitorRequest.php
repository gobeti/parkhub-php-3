<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterVisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'vehicle_plate' => 'nullable|string|max:20',
            'visit_date' => 'required|date',
            'purpose' => 'nullable|string|max:500',
        ];
    }
}
