<?php

/**
 * Realtime module routes (api/v1).
 * Loaded only when MODULE_REALTIME=true.
 *
 * SSE endpoint uses token query param for auth since EventSource
 * cannot set custom headers.
 */

use App\Http\Controllers\Api\SseController;
use Illuminate\Support\Facades\Route;

// SSE stream — auth via query param token (EventSource limitation)
Route::middleware(['module:realtime', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/sse', [SseController::class, 'stream']);
    Route::get('/sse/status', [SseController::class, 'status']);
});
