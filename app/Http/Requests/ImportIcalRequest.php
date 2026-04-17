<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts iCal payloads either as a multipart file upload or a raw string
 * body. Rules are conditional on the transport so callers can use whichever
 * shape fits their client without us routing to two separate endpoints.
 */
class ImportIcalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        if ($this->hasFile('file')) {
            return [
                'file' => 'required|file|mimes:ics,txt,calendar|max:2048',
            ];
        }

        return [
            'ical' => 'required|string|max:1048576',
        ];
    }
}
