<?php

/**
 * API Versioning module routes (api/v1).
 * Loaded only when MODULE_API_VERSIONING=true.
 */

use App\Http\Controllers\Api\ApiVersionController;
use Illuminate\Support\Facades\Route;

// Version info is public (frontend badge reads it without auth)
Route::middleware('module:api_versioning')->group(function () {
    Route::get('/version', [ApiVersionController::class, 'version']);
    Route::get('/changelog', [ApiVersionController::class, 'changelog']);
});
