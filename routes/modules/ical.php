<?php

/**
 * iCal module routes (api/v1).
 * Loaded only when MODULE_ICAL=true.
 */

use App\Http\Controllers\Api\ICalController;
use Illuminate\Support\Facades\Route;

// Authenticated routes
Route::middleware(['module:ical', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/calendar/ical', [ICalController::class, 'feed']);
    Route::post('/calendar/token', [ICalController::class, 'generateToken']);
});

// Public route (token-based, no auth)
Route::middleware(['module:ical', 'throttle:api'])->group(function () {
    Route::get('/calendar/ical/{token}', [ICalController::class, 'publicFeed']);
});
