<?php

declare(strict_types=1);

namespace App\Services\Authentication;

use App\Http\Controllers\Api\TwoFactorController;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Owns the login business logic extracted from
 * AuthController::login() (T-1742, pass 1).
 *
 * Pure extraction — error codes, status ordering, audit entries and
 * login-history rows match the previous inline controller implementation.
 */
final class AuthenticationService
{
    /**
     * @param  array{username: string, password: string, two_factor_code?: string|null}  $credentials
     * @param  array{ip: string|null, user_agent: string|null}  $context
     */
    public function attempt(array $credentials, array $context): AuthResult
    {
        $user = User::where('username', $credentials['username'])
            ->orWhere('email', $credentials['username'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            AuditLog::log([
                'action' => 'login_failed',
                'details' => ['username' => $credentials['username']],
                'ip_address' => $context['ip'],
            ]);

            return AuthResult::invalidCredentials();
        }

        if (! $user->is_active) {
            return AuthResult::accountDisabled($user);
        }

        if ($user->two_factor_enabled) {
            $code = $credentials['two_factor_code'] ?? null;

            if (! $code) {
                return AuthResult::requiresTwoFactor($user);
            }

            if (! TwoFactorController::validateLoginCode($user, (string) $code)) {
                return AuthResult::invalidTwoFactorCode($user);
            }
        }

        $user->update(['last_login' => now()]);
        $token = $user->createToken('auth-token');

        LoginHistory::create([
            'user_id' => $user->id,
            'ip_address' => $context['ip'] ?? null,
            'user_agent' => substr((string) ($context['user_agent'] ?? ''), 0, 512),
            'logged_in_at' => now(),
        ]);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'login',
            'ip_address' => $context['ip'] ?? null,
        ]);

        return AuthResult::success($user, $token);
    }
}
