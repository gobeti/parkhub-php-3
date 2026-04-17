<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the PATCH /api/v1/modules/{name}/config payload.
 *
 * Laravel validation only covers the envelope shape — the payload must
 * be a `{values: object}` wrapper so the controller gets a predictable
 * key to pull from. The deeper, per-module type/enum/format checks run
 * through `opis/json-schema` against the module's declared
 * `config_schema` inside the controller; FormRequest rules can't
 * express JSON Schema 2020-12 natively.
 *
 * Authorization is delegated to the `admin` middleware on the route
 * group, so `authorize()` returns true (anyone who reaches this
 * request has already cleared auth + admin).
 *
 * On validation failure we bypass the default Laravel 422 body and
 * return the same `{success, data, error, meta}` envelope the rest of
 * the modules API uses, with `error.code = CONFIG_VALIDATION_FAILED`
 * so the frontend can branch on it the same way it does for schema
 * errors raised by the controller — one error code, one handler.
 */
class UpdateModuleConfigRequest extends FormRequest
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
            'values' => 'required|array',
        ];
    }

    /**
     * Override the default 422 response so schema + rule violations
     * share the same envelope shape. Keeps the frontend's error-handling
     * single-path.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'data' => null,
                'error' => [
                    'code' => 'CONFIG_VALIDATION_FAILED',
                    'message' => 'Config payload failed validation.',
                    'details' => $validator->errors()->toArray(),
                ],
                'meta' => null,
            ], 422),
        );
    }
}
