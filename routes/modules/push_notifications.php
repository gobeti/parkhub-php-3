<?php

/**
 * Push Notifications module routes (api/v1).
 * Loaded only when MODULE_PUSH_NOTIFICATIONS=true.
 */

use App\Http\Controllers\Api\MiscController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// VAPID public key (no auth)
Route::middleware('module:push_notifications')->group(function () {
    Route::get('/push/vapid-key', [PublicController::class, 'vapidKey']);
});

Route::middleware(['module:push_notifications', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/push/subscribe', [MiscController::class, 'pushSubscribe']);
    Route::delete('/push/unsubscribe', [UserController::class, 'pushUnsubscribe']);
});
