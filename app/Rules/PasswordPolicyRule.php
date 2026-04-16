<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PasswordPolicyRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $minLength = (int) config('parkhub.password_min_length', 8);
        $requireUppercase = (bool) config('parkhub.password_require_uppercase', true);
        $requireNumber = (bool) config('parkhub.password_require_number', true);
        $requireSpecial = (bool) config('parkhub.password_require_special', false);

        if (strlen($value) < $minLength) {
            $fail("The :attribute must be at least {$minLength} characters.");

            return;
        }

        if (strlen($value) > 128) {
            $fail('The :attribute must not exceed 128 characters.');

            return;
        }

        if ($requireUppercase && ! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');

            return;
        }

        if (! preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');

            return;
        }

        if ($requireNumber && ! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');

            return;
        }

        if ($requireSpecial && ! preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $value)) {
            $fail('The :attribute must contain at least one special character.');

            return;
        }
    }
}
