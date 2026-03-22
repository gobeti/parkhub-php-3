<?php

/**
 * Absence Approval module routes (api/v1).
 * Loaded only when MODULE_ABSENCE_APPROVAL=true.
 */

use App\Http\Controllers\Api\AbsenceApprovalController;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:absence_approval', 'auth:sanctum', 'throttle:api'])->group(function () {
    // User endpoints
    Route::post('/absences/requests', [AbsenceApprovalController::class, 'store']);
    Route::get('/absences/my', [AbsenceApprovalController::class, 'myRequests']);

    // Admin endpoints
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/absences/pending', [AbsenceApprovalController::class, 'pending']);
        Route::put('/absences/{id}/approve', [AbsenceApprovalController::class, 'approve']);
        Route::put('/absences/{id}/reject', [AbsenceApprovalController::class, 'reject']);
    });
});
