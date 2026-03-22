<?php

/**
 * Lobby Display module routes (api/v1).
 * Loaded only when MODULE_LOBBY_DISPLAY=true.
 *
 * Public endpoint — no auth required.
 * Rate-limited to 10 requests/min per IP (kiosk polling).
 */

use App\Http\Controllers\Api\LobbyDisplayController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:lobby_display', 'throttle:lobby-display'])->group(function () {
    Route::get('/lots/{id}/display', [LobbyDisplayController::class, 'show']);
});
