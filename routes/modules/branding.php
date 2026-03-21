<?php

/**
 * Branding module routes (api/v1).
 * Loaded only when MODULE_BRANDING=true.
 */

use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\PublicController;
use Illuminate\Support\Facades\Route;

// Public branding routes
Route::middleware('module:branding')->group(function () {
    Route::get('/branding', [PublicController::class, 'branding']);
    Route::get('/branding/logo', [AdminSettingsController::class, 'serveBrandingLogo']);
    Route::get('/theme', [AdminSettingsController::class, 'getPublicTheme']);
});

// Admin branding management
Route::middleware(['module:branding', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin')->group(function () {
    Route::get('/branding', [AdminSettingsController::class, 'getBranding']);
    Route::put('/branding', [AdminSettingsController::class, 'updateBranding']);
    Route::post('/branding/logo', [AdminSettingsController::class, 'uploadBrandingLogo']);
});
