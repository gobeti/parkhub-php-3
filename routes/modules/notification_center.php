<?php

/**
 * Notification Center module routes (api/v1).
 * Loaded only when MODULE_NOTIFICATION_CENTER=true.
 *
 * Provides enriched notification list with icons, severity, date grouping,
 * and action URLs — matching the Rust backend's notification_center module.
 */

use App\Http\Controllers\Api\NotificationCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:notification_center', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/notifications/center', [NotificationCenterController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationCenterController::class, 'unreadCount']);
    Route::put('/notifications/center/read-all', [NotificationCenterController::class, 'markAllRead']);
    Route::delete('/notifications/center/{id}', [NotificationCenterController::class, 'destroy']);
});
