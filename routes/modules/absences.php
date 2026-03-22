<?php

/**
 * Absences module routes (api/v1).
 * Loaded only when MODULE_ABSENCES=true.
 */

use App\Http\Controllers\Api\AbsenceController;
use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['module:absences', 'auth:sanctum', 'throttle:api'])->group(function () {
    // Homeoffice (absence aliases for Rust frontend compat)
    Route::get('/homeoffice', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'pattern' => ['weekdays' => []],
            'single_days' => Absence::where('user_id', $user->id)
                ->where('absence_type', 'homeoffice')
                ->get()
                ->map(fn ($a) => ['id' => $a->id, 'date' => $a->start_date, 'reason' => $a->note]),
            'parkingSlot' => null,
        ]);
    });
    Route::post('/homeoffice/days', [AbsenceController::class, 'store']);
    Route::delete('/homeoffice/days/{id}', [AbsenceController::class, 'destroy']);
    Route::put('/homeoffice/pattern', [AbsenceController::class, 'update']);
    Route::get('/vacation', [AbsenceController::class, 'index']);
    Route::post('/vacation', [AbsenceController::class, 'store']);
    Route::delete('/vacation/{id}', [AbsenceController::class, 'destroy']);
    Route::get('/vacation/team', [AbsenceController::class, 'teamAbsences']);
    Route::get('/absences', [AbsenceController::class, 'index']);
    Route::post('/absences', [AbsenceController::class, 'store']);
    Route::put('/absences/{id}', [AbsenceController::class, 'update']);
    Route::delete('/absences/{id}', [AbsenceController::class, 'destroy']);
    Route::post('/absences/import', [AbsenceController::class, 'importIcal']);
    Route::post('/vacation/import', [AbsenceController::class, 'importIcal']);
    Route::get('/absences/pattern', [AbsenceController::class, 'getPattern']);
    Route::post('/absences/pattern', [AbsenceController::class, 'setPattern']);
    Route::get('/absences/team', [AbsenceController::class, 'teamAbsences']);

    // Admin absence approval
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/absences', [AbsenceController::class, 'adminIndex']);
        Route::get('/absences/pending', [AbsenceController::class, 'pending']);
        Route::patch('/absences/{id}/approve', [AbsenceController::class, 'approve']);
        Route::patch('/absences/{id}/reject', [AbsenceController::class, 'reject']);
    });
});
