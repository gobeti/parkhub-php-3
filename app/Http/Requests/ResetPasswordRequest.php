<?php

namespace App\Http\Requests;

use App\Rules\PasswordPolicyRule;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'string', new PasswordPolicyRule],
            'password_confirmation' => 'required|same:password',
        ];
    }
}
