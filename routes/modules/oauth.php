<?php

/**
 * OAuth module routes (api/v1).
 * Loaded only when MODULE_OAUTH=true (disabled by default — requires OAuth credentials).
 *
 * Provides social login via Google and GitHub.
 */

use App\Http\Controllers\Api\OAuthController;
use Illuminate\Support\Facades\Route;

// Public OAuth routes (no auth required — these initiate and complete the OAuth flow)
Route::middleware(['module:oauth', 'throttle:auth'])->group(function () {
    Route::get('/auth/oauth/providers', [OAuthController::class, 'providers']);
    Route::get('/auth/oauth/google', [OAuthController::class, 'googleRedirect']);
    Route::get('/auth/oauth/google/callback', [OAuthController::class, 'googleCallback']);
    Route::get('/auth/oauth/github', [OAuthController::class, 'githubRedirect']);
    Route::get('/auth/oauth/github/callback', [OAuthController::class, 'githubCallback']);
});
