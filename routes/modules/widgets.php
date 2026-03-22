<?php

/**
 * Admin Dashboard Widgets module routes (api/v1).
 * Loaded only when MODULE_WIDGETS=true.
 */

use App\Http\Controllers\Api\WidgetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:widgets', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::get('/admin/widgets', [WidgetController::class, 'index']);
    Route::put('/admin/widgets', [WidgetController::class, 'update']);
    Route::get('/admin/widgets/data/{widget_id}', [WidgetController::class, 'data']);
});
