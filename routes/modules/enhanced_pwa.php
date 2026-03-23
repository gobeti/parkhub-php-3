<?php

/**
 * Enhanced PWA module routes (api/v1).
 * Loaded only when MODULE_ENHANCED_PWA=true (enabled by default).
 *
 * Provides dynamic PWA manifest and offline data endpoints.
 */

use App\Http\Controllers\Api\PWAController;
use Illuminate\Support\Facades\Route;

// Public manifest (no auth — served to browsers)
Route::middleware(['module:enhanced_pwa'])->group(function () {
    Route::get('/pwa/manifest', [PWAController::class, 'manifest']);
});

// Protected offline data (requires auth)
Route::middleware(['module:enhanced_pwa', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/pwa/offline-data', [PWAController::class, 'offlineData']);
});
