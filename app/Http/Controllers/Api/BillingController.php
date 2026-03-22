<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    /**
     * GET /api/v1/admin/billing/by-cost-center — billing breakdown by cost center.
     */
    public function byCostCenter(): JsonResponse
    {
        $data = User::select('cost_center', 'department')
            ->selectRaw('COUNT(DISTINCT users.id) as user_count')
            ->leftJoin('bookings', 'bookings.user_id', '=', 'users.id')
            ->selectRaw('COUNT(bookings.id) as total_bookings')
            ->selectRaw('COALESCE(SUM(bookings.total_price), 0) as total_amount')
            ->whereNotNull('cost_center')
            ->where('cost_center', '!=', '')
            ->groupBy('cost_center', 'department')
            ->orderBy('cost_center')
            ->get()
            ->map(fn ($row) => [
                'cost_center' => $row->cost_center,
                'department' => $row->department ?? '',
                'user_count' => (int) $row->user_count,
                'total_bookings' => (int) $row->total_bookings,
                'total_credits_used' => 0,
                'total_amount' => round((float) $row->total_amount, 2),
                'currency' => 'EUR',
            ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/admin/billing/by-department — billing breakdown by department.
     */
    public function byDepartment(): JsonResponse
    {
        $data = User::select('department')
            ->selectRaw('COUNT(DISTINCT users.id) as user_count')
            ->leftJoin('bookings', 'bookings.user_id', '=', 'users.id')
            ->selectRaw('COUNT(bookings.id) as total_bookings')
            ->selectRaw('COALESCE(SUM(bookings.total_price), 0) as total_amount')
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->groupBy('department')
            ->orderBy('department')
            ->get()
            ->map(fn ($row) => [
                'department' => $row->department ?? '',
                'user_count' => (int) $row->user_count,
                'total_bookings' => (int) $row->total_bookings,
                'total_credits_used' => 0,
                'total_amount' => round((float) $row->total_amount, 2),
                'currency' => 'EUR',
            ]);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/admin/billing/export — CSV export of billing data.
     */
    public function export(): StreamedResponse
    {
        $data = User::select('cost_center', 'department', 'users.id')
            ->selectRaw('users.name')
            ->selectRaw('users.email')
            ->leftJoin('bookings', 'bookings.user_id', '=', 'users.id')
            ->selectRaw('COUNT(bookings.id) as total_bookings')
            ->selectRaw('COALESCE(SUM(bookings.total_price), 0) as total_amount')
            ->groupBy('users.id', 'cost_center', 'department', 'users.name', 'users.email')
            ->orderBy('cost_center')
            ->get();

        return response()->streamDownload(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Cost Center', 'Department', 'Name', 'Email', 'Bookings', 'Amount (EUR)']);

            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->cost_center ?? '',
                    $row->department ?? '',
                    $row->name,
                    $row->email,
                    $row->total_bookings,
                    number_format((float) $row->total_amount, 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, 'billing-export-'.date('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * POST /api/v1/admin/billing/allocate — assign cost center to users.
     */
    public function allocate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'uuid|exists:users,id',
            'cost_center' => 'required|string|max:100',
            'department' => 'nullable|string|max:100',
        ]);

        $updated = User::whereIn('id', $validated['user_ids'])->update([
            'cost_center' => $validated['cost_center'],
            'department' => $validated['department'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'updated' => $updated,
            ],
        ]);
    }
}
