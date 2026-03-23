<?php

/**
 * Webhooks v2 module routes (api/v1).
 * Loaded only when MODULE_WEBHOOKS_V2=true (disabled by default — enterprise feature).
 *
 * Provides CRUD, test, and delivery log for v2 webhooks with HMAC-SHA256 signing.
 */

use App\Http\Controllers\Api\WebhookV2Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:webhooks_v2', 'auth:sanctum', 'admin', 'throttle:api'])->prefix('admin')->group(function () {
    Route::get('/webhooks-v2', [WebhookV2Controller::class, 'index']);
    Route::post('/webhooks-v2', [WebhookV2Controller::class, 'store']);
    Route::get('/webhooks-v2/{id}', [WebhookV2Controller::class, 'show']);
    Route::put('/webhooks-v2/{id}', [WebhookV2Controller::class, 'update']);
    Route::delete('/webhooks-v2/{id}', [WebhookV2Controller::class, 'destroy']);
    Route::post('/webhooks-v2/{id}/test', [WebhookV2Controller::class, 'test']);
    Route::get('/webhooks-v2/{id}/deliveries', [WebhookV2Controller::class, 'deliveries']);
});
