<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Outcome of AuthenticationService::attempt().
 *
 * The service returns an enum-tagged value object so the controller can
 * shape the HTTP envelope without re-implementing any auth decision.
 */
enum AuthOutcome: string
{
    case Success = 'success';
    case RequiresTwoFactor = 'requires_2fa';
    case InvalidCredentials = 'invalid_credentials';
    case AccountDisabled = 'account_disabled';
    case InvalidTwoFactorCode = 'invalid_2fa_code';
}

final class AuthResult
{
    public function __construct(
        public readonly AuthOutcome $outcome,
        public readonly ?User $user = null,
        public readonly ?NewAccessToken $token = null,
    ) {}

    public static function success(User $user, NewAccessToken $token): self
    {
        return new self(AuthOutcome::Success, $user, $token);
    }

    public static function requiresTwoFactor(User $user): self
    {
        return new self(AuthOutcome::RequiresTwoFactor, $user);
    }

    public static function invalidCredentials(): self
    {
        return new self(AuthOutcome::InvalidCredentials);
    }

    public static function accountDisabled(User $user): self
    {
        return new self(AuthOutcome::AccountDisabled, $user);
    }

    public static function invalidTwoFactorCode(User $user): self
    {
        return new self(AuthOutcome::InvalidTwoFactorCode, $user);
    }
}
