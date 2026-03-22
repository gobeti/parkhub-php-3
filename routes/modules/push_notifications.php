<?php

/**
 * Push Notifications module routes (api/v1).
 * Loaded only when MODULE_PUSH_NOTIFICATIONS=true.
 */

use App\Http\Controllers\Api\PushController;
use Illuminate\Support\Facades\Route;

// VAPID public key (no auth)
Route::middleware('module:push_notifications')->group(function () {
    Route::get('/push/vapid-key', [PushController::class, 'vapidKey']);
});

Route::middleware(['module:push_notifications', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/push/subscribe', [PushController::class, 'subscribe']);
    Route::delete('/push/unsubscribe', [PushController::class, 'unsubscribe']);
});
