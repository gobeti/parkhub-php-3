<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkExportInvoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'booking_ids' => 'required|array|min:1|max:50',
            'booking_ids.*' => 'uuid',
        ];
    }
}
