<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    /**
     * Create a named API key with optional expiry and scoped abilities.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'expires_at' => 'sometimes|nullable|date|after:now',
            'abilities' => 'sometimes|array',
            'abilities.*' => 'string|max:100',
        ]);

        $abilities = $request->input('abilities', ['*']);
        $expiresAt = $request->input('expires_at') ? Carbon::parse($request->expires_at) : null;

        $token = $request->user()->createToken(
            $request->name,
            $abilities,
            $expiresAt
        );

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'api_key_created',
            'details' => ['name' => $request->name],
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'id' => $token->accessToken->id,
            'name' => $request->name,
            'token' => $token->plainTextToken,
            'abilities' => $abilities,
            'expires_at' => $expiresAt?->toISOString(),
            'message' => 'Store this token securely — it will not be shown again.',
        ], 201);
    }

    /**
     * List API keys (token values masked).
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->where('name', '!=', 'auth-token')
            ->orderBy('created_at', 'desc')
            ->get();

        $keys = $tokens->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toISOString(),
            'created_at' => $token->created_at?->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
        ]);

        return response()->json($keys);
    }

    /**
     * Revoke a specific API key.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (! $token) {
            return response()->json([
                'error' => 'API_KEY_NOT_FOUND',
                'message' => 'API key not found.',
            ], 404);
        }

        $tokenName = $token->name;
        $token->delete();

        AuditLog::log([
            'user_id' => $request->user()->id,
            'username' => $request->user()->username,
            'action' => 'api_key_revoked',
            'details' => ['name' => $tokenName],
            'ip_address' => $request->ip(),
        ]);

        return response()->json(['message' => 'API key revoked.']);
    }
}
