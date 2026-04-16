<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * OAuth / Social Login controller.
 *
 * Supports Google and GitHub providers. Each provider is only available
 * when the corresponding OAUTH_*_CLIENT_ID env vars are set.
 *
 * Flow:
 *   1. GET /api/v1/auth/oauth/{provider}       → redirect to consent screen
 *   2. GET /api/v1/auth/oauth/{provider}/callback → exchange code, create/link user, redirect with token
 *   3. GET /api/v1/auth/oauth/providers         → list available providers (no secrets)
 */
class OAuthController extends Controller
{
    /**
     * Return which OAuth providers are configured (no secrets exposed).
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'google' => $this->isProviderConfigured('google'),
            'github' => $this->isProviderConfigured('github'),
        ]);
    }

    /**
     * Redirect to Google OAuth consent screen.
     */
    public function googleRedirect(): RedirectResponse
    {
        $this->ensureProviderConfigured('google');

        $params = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->callbackUrl('google'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'offline',
            'state' => $this->generateState(),
        ]);

        return redirect("https://accounts.google.com/o/oauth2/v2/auth?{$params}");
    }

    /**
     * Handle Google OAuth callback.
     */
    public function googleCallback(Request $request): RedirectResponse
    {
        $this->ensureProviderConfigured('google');
        $this->validateCallback($request);

        $code = $request->query('code');

        // Exchange code for tokens
        $tokenResponse = $this->httpPost('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'redirect_uri' => $this->callbackUrl('google'),
            'grant_type' => 'authorization_code',
        ]);

        if (! isset($tokenResponse['access_token'])) {
            return $this->errorRedirect('OAuth token exchange failed');
        }

        // Fetch user info
        $userInfo = $this->httpGet('https://www.googleapis.com/oauth2/v2/userinfo', $tokenResponse['access_token']);

        if (! isset($userInfo['email'])) {
            return $this->errorRedirect('Could not retrieve email from Google');
        }

        return $this->handleOAuthUser('google', $userInfo['id'] ?? $userInfo['email'], [
            'email' => $userInfo['email'],
            'name' => $userInfo['name'] ?? $userInfo['email'],
            'picture' => $userInfo['picture'] ?? null,
        ], $request);
    }

    /**
     * Redirect to GitHub OAuth consent screen.
     */
    public function githubRedirect(): RedirectResponse
    {
        $this->ensureProviderConfigured('github');

        $params = http_build_query([
            'client_id' => config('services.github.client_id'),
            'redirect_uri' => $this->callbackUrl('github'),
            'scope' => 'user:email',
            'state' => $this->generateState(),
        ]);

        return redirect("https://github.com/login/oauth/authorize?{$params}");
    }

    /**
     * Handle GitHub OAuth callback.
     */
    public function githubCallback(Request $request): RedirectResponse
    {
        $this->ensureProviderConfigured('github');
        $this->validateCallback($request);

        $code = $request->query('code');

        // Exchange code for access token
        $tokenResponse = $this->httpPost('https://github.com/login/oauth/access_token', [
            'client_id' => config('services.github.client_id'),
            'client_secret' => config('services.github.client_secret'),
            'code' => $code,
            'redirect_uri' => $this->callbackUrl('github'),
        ], ['Accept' => 'application/json']);

        if (! isset($tokenResponse['access_token'])) {
            return $this->errorRedirect('OAuth token exchange failed');
        }

        // Fetch user info
        $userInfo = $this->httpGet('https://api.github.com/user', $tokenResponse['access_token']);

        // GitHub may not return email in profile — fetch from emails endpoint
        $email = $userInfo['email'] ?? null;
        if (! $email) {
            $emails = $this->httpGet('https://api.github.com/user/emails', $tokenResponse['access_token']);
            if (is_array($emails)) {
                foreach ($emails as $e) {
                    if (! empty($e['primary']) && ! empty($e['verified'])) {
                        $email = $e['email'];
                        break;
                    }
                }
            }
        }

        if (! $email) {
            return $this->errorRedirect('Could not retrieve email from GitHub');
        }

        return $this->handleOAuthUser('github', (string) ($userInfo['id'] ?? $email), [
            'email' => $email,
            'name' => $userInfo['name'] ?? $userInfo['login'] ?? $email,
            'picture' => $userInfo['avatar_url'] ?? null,
        ], $request);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            'google' => ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret')),
            'github' => ! empty(config('services.github.client_id')) && ! empty(config('services.github.client_secret')),
            default => false,
        };
    }

    private function ensureProviderConfigured(string $provider): void
    {
        if (! $this->isProviderConfigured($provider)) {
            abort(404, "OAuth provider '{$provider}' is not configured");
        }
    }

    private function callbackUrl(string $provider): string
    {
        return rtrim(config('app.url'), '/')."/api/v1/auth/oauth/{$provider}/callback";
    }

    private function generateState(): string
    {
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        return $state;
    }

    private function validateCallback(Request $request): void
    {
        if ($request->has('error')) {
            abort(400, 'OAuth authorization denied: '.($request->query('error_description') ?? $request->query('error')));
        }
    }

    /**
     * Find or create user from OAuth profile, generate token, redirect to frontend.
     */
    private function handleOAuthUser(string $provider, string $providerId, array $profile, Request $request): RedirectResponse
    {
        $user = User::where('email', $profile['email'])->first();

        if ($user) {
            // Existing user — update last login
            $user->update(['last_login' => now()]);
            if (! empty($profile['picture']) && ! $user->picture) {
                $user->update(['picture' => $profile['picture']]);
            }
        } else {
            // New user — create account
            $username = Str::slug($profile['name'] ?? 'user', '_').'_'.Str::random(4);
            $user = User::create([
                'username' => $username,
                'email' => $profile['email'],
                'password' => Hash::make(Str::random(32)), // random password — user uses OAuth
                'name' => $profile['name'],
                'picture' => $profile['picture'],
                'is_active' => true,
                'preferences' => ['language' => 'en', 'theme' => 'system', 'notifications_enabled' => true],
            ]);
            $user->role = 'user';
            $user->save();
        }

        if (! $user->is_active) {
            return $this->errorRedirect('Account is disabled');
        }

        $token = $user->createToken('oauth-'.$provider);

        AuditLog::log([
            'user_id' => $user->id,
            'username' => $user->username,
            'action' => 'oauth_login',
            'details' => ['provider' => $provider],
            'ip_address' => $request->ip(),
        ]);

        // Redirect to frontend with token
        $frontendUrl = rtrim(config('app.url'), '/');

        return redirect("{$frontendUrl}/oauth/callback?token={$token->plainTextToken}");
    }

    private function errorRedirect(string $message): RedirectResponse
    {
        $frontendUrl = rtrim(config('app.url'), '/');

        return redirect("{$frontendUrl}/login?oauth_error=".urlencode($message));
    }

    /**
     * HTTP POST helper.
     */
    private function httpPost(string $url, array $data, array $extraHeaders = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => array_merge([
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
            ], array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($extraHeaders), array_values($extraHeaders))),
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?: [];
    }

    /**
     * HTTP GET helper with Bearer token.
     */
    private function httpGet(string $url, string $token): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                "Authorization: Bearer {$token}",
                'User-Agent: ParkHub-PHP',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?: [];
    }
}
