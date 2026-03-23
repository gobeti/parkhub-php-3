<?php

/**
 * Plugin System module routes (api/v1).
 * Loaded only when MODULE_PLUGINS=true.
 */

use App\Http\Controllers\Api\PluginController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:plugins', 'auth:sanctum', 'throttle:api', 'admin'])->group(function () {
    Route::get('/admin/plugins', [PluginController::class, 'index']);
    Route::put('/admin/plugins/{id}/toggle', [PluginController::class, 'toggle']);
    Route::get('/admin/plugins/{id}/config', [PluginController::class, 'getConfig']);
    Route::put('/admin/plugins/{id}/config', [PluginController::class, 'updateConfig']);
});
