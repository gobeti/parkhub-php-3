<?php

/**
 * QR Codes module routes (api/v1).
 * Loaded only when MODULE_QR_CODES=true.
 */

use App\Http\Controllers\Api\LotController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:qr_codes', 'auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/lots/{id}/qr', [LotController::class, 'qrCode']);
    Route::get('/lots/{lotId}/slots/{slotId}/qr', [LotController::class, 'slotQrCode']);
});
