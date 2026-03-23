<?php

/**
 * SSO module routes (api/v1).
 * Loaded only when MODULE_SSO=true (disabled by default — enterprise feature).
 *
 * Provides SAML/SSO enterprise authentication.
 */

use App\Http\Controllers\Api\SSOController;
use Illuminate\Support\Facades\Route;

// Public SSO routes (no auth required — these initiate and complete the SSO flow)
Route::middleware(['module:sso', 'throttle:auth'])->group(function () {
    Route::get('/auth/sso/providers', [SSOController::class, 'providers']);
    Route::get('/auth/sso/{provider}/login', [SSOController::class, 'login']);
    Route::post('/auth/sso/{provider}/callback', [SSOController::class, 'callback']);
});

// Admin SSO management
Route::middleware(['module:sso', 'auth:sanctum', 'admin', 'throttle:api'])->prefix('admin')->group(function () {
    Route::put('/sso/{provider}', [SSOController::class, 'upsert']);
    Route::delete('/sso/{provider}', [SSOController::class, 'destroy']);
});
