<?php

/**
 * GDPR module routes (api/v1).
 * Loaded only when MODULE_GDPR=true.
 */

use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public privacy/impressum
Route::middleware('module:gdpr')->group(function () {
    Route::get('/legal/impressum', [AdminSettingsController::class, 'publicImpress']);
});

Route::middleware(['module:gdpr', 'auth:sanctum', 'throttle:api'])->group(function () {
    // Account deletion
    Route::delete('/users/me/delete', [AuthController::class, 'deleteAccount']);

    // GDPR Art. 17 — Right to Erasure
    Route::post('/users/me/anonymize', [UserController::class, 'anonymizeAccount']);

    // Admin privacy settings
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/privacy', [AdminSettingsController::class, 'getPrivacy']);
        Route::put('/privacy', [AdminSettingsController::class, 'updatePrivacy']);
        Route::get('/impressum', [AdminSettingsController::class, 'getImpress']);
        Route::put('/impressum', [AdminSettingsController::class, 'updateImpress']);
    });
});
