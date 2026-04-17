<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // Accept both 'type' (Rust API parity) and 'absence_type'
        $this->merge([
            'absence_type' => $this->input('absence_type', $this->input('type')),
        ]);
    }

    public function rules(): array
    {
        return [
            'absence_type' => 'required|in:homeoffice,vacation,sick,training,other',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
        ];
    }
}
