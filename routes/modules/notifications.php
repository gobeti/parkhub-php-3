<?php

/**
 * Notifications module routes (api/v1).
 * Loaded only when MODULE_NOTIFICATIONS=true.
 */

use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:notifications', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/notifications', [UserController::class, 'notifications']);
    Route::put('/notifications/{id}/read', [UserController::class, 'markNotificationRead']);
    Route::post('/notifications/read-all', [UserController::class, 'markAllNotificationsRead']);
});
