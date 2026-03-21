<?php

/**
 * Webhooks module routes (api/v1).
 * Loaded only when MODULE_WEBHOOKS=true.
 */

use App\Http\Controllers\Api\MiscController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:webhooks', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/webhooks', [MiscController::class, 'webhooks']);
    Route::post('/webhooks', [MiscController::class, 'createWebhook']);
    Route::put('/webhooks/{id}', [MiscController::class, 'updateWebhook']);
    Route::delete('/webhooks/{id}', [MiscController::class, 'deleteWebhook']);
    Route::post('/webhooks/{id}/test', [MiscController::class, 'testWebhook']);
});
