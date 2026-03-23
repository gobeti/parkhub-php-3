<?php

/**
 * Booking Sharing & Guest Invites module routes (api/v1).
 * Loaded only when MODULE_SHARING=true.
 */

use App\Http\Controllers\Api\SharingController;
use Illuminate\Support\Facades\Route;

// Public share view (no auth required)
Route::middleware('module:sharing')->group(function () {
    Route::get('/shared/{code}', [SharingController::class, 'viewShare']);
});

// Protected sharing routes
Route::middleware(['module:sharing', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/bookings/{id}/share', [SharingController::class, 'createShare']);
    Route::post('/bookings/{id}/invite', [SharingController::class, 'inviteGuest']);
    Route::delete('/bookings/{id}/share', [SharingController::class, 'revokeShare']);
});
