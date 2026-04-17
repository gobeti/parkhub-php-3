<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the PATCH /api/v1/admin/modules/{name} payload.
 *
 * Body is a single `runtime_enabled: bool`. The controller layer owns
 * module-existence + toggleable checks — this request's job is just
 * to make sure the JSON shape is sane before we touch the DB.
 *
 * Authorization is delegated to the `admin` middleware on the route
 * group, so `authorize()` returns true (anyone who reaches the request
 * has already cleared auth + admin).
 */
class UpdateModuleRuntimeStateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'runtime_enabled' => 'required|boolean',
        ];
    }
}
