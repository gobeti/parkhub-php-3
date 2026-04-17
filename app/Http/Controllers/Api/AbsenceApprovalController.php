<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectAbsenceRequest;
use App\Http\Requests\StoreAbsenceApprovalRequest;
use App\Models\Absence;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsenceApprovalController extends Controller
{
    /**
     * POST /api/v1/absences/requests — submit absence request (status=pending).
     */
    public function store(StoreAbsenceApprovalRequest $request): JsonResponse
    {
        $absence = Absence::create([
            'user_id' => $request->user()->id,
            'absence_type' => $request->input('absence_type'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'note' => $request->input('reason'),
            'source' => 'approval_request',
            'status' => Absence::STATUS_PENDING,
        ]);

        return response()->json(array_merge($absence->toArray(), [
            'user_name' => $request->user()->name,
            'reason' => $absence->note,
        ]), 201);
    }

    /**
     * GET /api/v1/absences/my — user's own absence requests with status.
     */
    public function myRequests(Request $request): JsonResponse
    {
        $absences = Absence::where('user_id', $request->user()->id)
            ->where('source', 'approval_request')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($a) => array_merge($a->toArray(), [
                'user_name' => $request->user()->name,
                'reason' => $a->note,
                'reviewer_comment' => $a->reviewer_comment ?? null,
            ]));

        return response()->json($absences);
    }

    /**
     * GET /api/v1/admin/absences/pending — list all pending requests (admin).
     */
    public function pending(): JsonResponse
    {
        $absences = Absence::with('user')
            ->where('source', 'approval_request')
            ->where('status', Absence::STATUS_PENDING)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($a) => array_merge($a->toArray(), [
                'user_name' => $a->user?->name,
                'reason' => $a->note,
            ]));

        return response()->json($absences);
    }

    /**
     * PUT /api/v1/admin/absences/{id}/approve — approve with optional comment (admin).
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $absence = Absence::where('status', Absence::STATUS_PENDING)->findOrFail($id);

        $absence->update([
            'status' => Absence::STATUS_APPROVED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_comment' => $request->input('comment'),
        ]);

        // Notify the requester
        if ($absence->user_id !== $request->user()->id) {
            Notification::create([
                'user_id' => $absence->user_id,
                'title' => 'Absence Approved',
                'message' => 'Your '.$absence->absence_type.' request ('.$absence->start_date.' - '.$absence->end_date.') has been approved.',
                'type' => 'absence_approval',
            ]);
        }

        return response()->json(array_merge($absence->fresh()->toArray(), [
            'reason' => $absence->note,
        ]));
    }

    /**
     * PUT /api/v1/admin/absences/{id}/reject — reject with reason (admin).
     */
    public function reject(RejectAbsenceRequest $request, string $id): JsonResponse
    {
        $absence = Absence::where('status', Absence::STATUS_PENDING)->findOrFail($id);

        $absence->update([
            'status' => Absence::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'reviewer_comment' => $request->input('reason'),
        ]);

        // Notify the requester
        if ($absence->user_id !== $request->user()->id) {
            Notification::create([
                'user_id' => $absence->user_id,
                'title' => 'Absence Rejected',
                'message' => 'Your '.$absence->absence_type.' request ('.$absence->start_date.' - '.$absence->end_date.') was rejected: '.$request->input('reason'),
                'type' => 'absence_rejection',
            ]);
        }

        return response()->json(array_merge($absence->fresh()->toArray(), [
            'reason' => $absence->note,
        ]));
    }
}
