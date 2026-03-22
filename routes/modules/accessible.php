<?php

/**
 * Accessible Parking module routes (api/v1).
 * Loaded only when MODULE_ACCESSIBLE=true.
 */

use App\Http\Controllers\Api\AccessibleParkingController;
use Illuminate\Support\Facades\Route;

// Public stats (authenticated)
Route::middleware(['module:accessible', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/lots/{id}/slots/accessible', [AccessibleParkingController::class, 'accessibleSlots']);
    Route::get('/bookings/accessible-stats', [AccessibleParkingController::class, 'stats']);
    Route::put('/users/me/accessibility-needs', [AccessibleParkingController::class, 'updateNeeds']);
});

// Admin routes
Route::middleware(['module:accessible', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::put('/admin/lots/{id}/slots/{slot}/accessible', [AccessibleParkingController::class, 'toggleAccessible']);
});
