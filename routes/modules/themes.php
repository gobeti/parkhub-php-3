<?php

/**
 * Themes module routes (api/v1).
 * Loaded only when MODULE_THEMES=true.
 */

use App\Http\Controllers\Api\ThemeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:themes', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/preferences/theme', [ThemeController::class, 'show']);
    Route::put('/preferences/theme', [ThemeController::class, 'update']);
});
