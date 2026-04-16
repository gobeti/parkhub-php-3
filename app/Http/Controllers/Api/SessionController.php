<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * List active Sanctum tokens for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderBy('created_at', 'desc')->get();
        $currentTokenId = $request->user()->currentAccessToken()->id;

        $sessions = $tokens->map(fn ($token) => [
            'id' => $token->id,
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toISOString(),
            'created_at' => $token->created_at?->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
            'is_current' => $token->id === $currentTokenId,
        ]);

        return response()->json($sessions);
    }

    /**
     * Revoke a specific token.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (! $token) {
            return response()->json([
                'error' => 'TOKEN_NOT_FOUND',
                'message' => 'Session not found.',
            ], 404);
        }

        $currentTokenId = $request->user()->currentAccessToken()->id;
        if ($token->id === $currentTokenId) {
            return response()->json([
                'error' => 'CANNOT_REVOKE_CURRENT',
                'message' => 'Cannot revoke the current session. Use logout instead.',
            ], 400);
        }

        $token->delete();

        return response()->json(['message' => 'Session revoked.']);
    }

    /**
     * Revoke all tokens except the current one.
     */
    public function destroyAll(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $deleted = $request->user()->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json([
            'message' => 'All other sessions revoked.',
            'revoked_count' => $deleted,
        ]);
    }
}
