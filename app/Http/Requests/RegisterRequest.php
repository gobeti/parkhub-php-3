<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|min:3|max:50|unique:users|alpha_dash',
            'email' => 'required|email|max:255|unique:users',
            'password' => ['required', 'string', 'min:8', 'max:128', 'confirmed', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'],
            'name' => 'required|string|max:255',
        ];
    }
}
