<?php

/**
 * Fleet Management module routes (api/v1).
 * Loaded only when MODULE_FLEET=true.
 */

use App\Http\Controllers\Api\FleetController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:fleet', 'auth:sanctum', 'throttle:api', 'admin'])->prefix('admin/fleet')->group(function () {
    Route::get('/', [FleetController::class, 'index']);
    Route::get('/stats', [FleetController::class, 'stats']);
    Route::put('/{id}/flag', [FleetController::class, 'flag']);
});
