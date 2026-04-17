<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\User;
use App\Support\TenantScope;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Owns CSV export assembly extracted from AdminReportController
 * (T-1742, pass 1).
 *
 * Tenant scoping is preserved exactly as the legacy controller enforced
 * it: defense-in-depth `when(TenantScope::currentId(), ...)` on top of
 * the `BelongsToTenant` global scope.
 */
final class ReportExportService
{
    /**
     * Streamed export of bookings filtered by the current tenant.
     */
    public function exportBookingsCsv(): StreamedResponse
    {
        $headers = ['ID', 'User', 'Lot', 'Slot', 'Vehicle', 'Start', 'End', 'Status', 'Type'];
        $tenantId = TenantScope::currentId();

        // Tenant filter MUST be applied to the query builder before the cursor
        // is opened — every row the generator emits flows through the CSV.
        // BelongsToTenantScope handles this when the flag is on; we also pin
        // an explicit predicate so a future withoutGlobalScope(...) wouldn't
        // silently leak cross-tenant rows into the export.
        $query = Booking::with('user')
            ->orderBy('start_time', 'desc')
            ->when($tenantId !== null, fn ($q) => $q->where('bookings.tenant_id', $tenantId));

        return response()->streamDownload(function () use ($headers, $query) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($query->cursor() as $b) {
                fputcsv($output, [
                    $b->id,
                    $this->csvSafe($b->user?->name ?? 'Guest'),
                    $this->csvSafe($b->lot_name),
                    $this->csvSafe($b->slot_number),
                    $this->csvSafe($b->vehicle_plate ?? ''),
                    $b->start_time?->format('Y-m-d H:i'),
                    $b->end_time?->format('Y-m-d H:i'),
                    $b->status,
                    $b->booking_type,
                ]);
            }

            fclose($output);
        }, 'bookings-export.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Buffered CSV export of users filtered by the current tenant.
     */
    public function exportUsersCsv(): Response
    {
        $tenantId = TenantScope::currentId();
        $users = User::orderBy('name')
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->get();

        $headers = ['ID', 'Username', 'Name', 'Email', 'Role', 'Department', 'Active', 'Created'];
        $rows = $users->map(fn ($u) => [
            $u->id,
            $this->csvSafe($u->username),
            $this->csvSafe($u->name),
            $this->csvSafe($u->email),
            $u->role,
            $this->csvSafe($u->department ?? ''),
            $u->is_active ? 'yes' : 'no',
            optional($u->created_at)->format('Y-m-d'),
        ]);

        $output = fopen('php://output', 'w');
        ob_start();
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        $csv = ob_get_clean();

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users-export.csv"',
        ]);
    }

    /**
     * Prefix cells that begin with a CSV formula sigil so spreadsheet
     * importers don't interpret exported data as executable formulas.
     */
    private function csvSafe(mixed $value): string
    {
        $str = (string) $value;
        if (preg_match('/^[=+\-@\t\r]/', $str)) {
            return "'".$str;
        }

        return $str;
    }
}
