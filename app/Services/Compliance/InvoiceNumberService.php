<?php

declare(strict_types=1);

namespace App\Services\Compliance;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Allocates fortlaufende (sequentially ascending, gap-free) invoice numbers
 * as required by German § 14 UStG.
 *
 * # Policy
 *
 * Format: `YYYY-NNNNNNN` (four-digit year, dash, seven-digit zero-padded
 * counter). Example: `2026-0000042`.
 *
 * Per-year reset: the counter restarts at 1 on January 1. This is explicitly
 * permitted by § 14 UStG (and reaffirmed in the BMF circular on electronic
 * invoicing) because the year-segment of the rendered number keeps the
 * combined identifier globally unique within the tax subject, which is what
 * the statute actually requires. The alternative (monotonic across years) is
 * equally compliant; yearly reset is chosen here for operator UX.
 *
 * # Atomicity
 *
 * The lookup-or-allocate is a single DB transaction with SELECT ... FOR UPDATE
 * on the per-year counter row. Combined with the booking_invoice_numbers
 * idempotence table, this guarantees:
 *
 *   - no two bookings ever receive the same number,
 *   - no counter value is advanced without being persisted,
 *   - re-rendering an invoice for the same booking returns the same number
 *     and never burns a fresh counter entry.
 *
 * Gaps are therefore impossible regardless of concurrent requests, retries,
 * or process crashes between allocation and response delivery.
 *
 * # Storno / cancellation
 *
 * Cancelled invoices do **not** free up their number — the gap is filled by
 * a storno record that references the original with its own fresh number.
 * That flow is out of scope for this service.
 */
final class InvoiceNumberService
{
    /**
     * Look up an existing invoice number for the given booking, or atomically
     * allocate and persist the next fortlaufende number for the supplied year
     * if none has been issued.
     *
     * @param  string  $bookingId  UUID of the booking the invoice is for
     * @param  int|null  $year  Fiscal year of the invoice; defaults to the
     *                          booking's invoice year (typically the booking
     *                          creation year) or the current year
     * @return string The invoice number, e.g. `2026-0000042`
     */
    public function getOrAssign(string $bookingId, ?int $year = null): string
    {
        $year ??= (int) Carbon::now()->year;

        // Fast path outside a transaction: if this booking already has an
        // invoice number the answer is immutable, so a single indexed read
        // on the primary key is all we need. This avoids taking a row-level
        // lock on the counter table for every re-download.
        $existing = DB::table('booking_invoice_numbers')
            ->where('booking_id', $bookingId)
            ->value('invoice_number');

        if ($existing !== null) {
            return (string) $existing;
        }

        // Slow path: allocate under SELECT ... FOR UPDATE. Wrapped in a
        // transaction so the counter bump and the booking→number mapping
        // land together — no scenario can leave the counter advanced without
        // a corresponding mapping row (which would be a legal gap) or the
        // mapping row without the counter bump (which would allow duplicate
        // numbers).
        return DB::transaction(function () use ($bookingId, $year) {
            // Re-check inside the transaction: a concurrent request may have
            // allocated for this same booking between the fast-path read and
            // the transaction start.
            $existing = DB::table('booking_invoice_numbers')
                ->where('booking_id', $bookingId)
                ->lockForUpdate()
                ->value('invoice_number');

            if ($existing !== null) {
                return (string) $existing;
            }

            $row = DB::table('invoice_counters')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                DB::table('invoice_counters')->insert([
                    'year' => $year,
                    'counter' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $row = DB::table('invoice_counters')
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();
            }

            $current = (int) $row->counter;
            $next = $current + 1;

            DB::table('invoice_counters')
                ->where('year', $year)
                ->update([
                    'counter' => $next,
                    'updated_at' => now(),
                ]);

            $number = sprintf('%04d-%07d', $year, $next);

            DB::table('booking_invoice_numbers')->insert([
                'booking_id' => $bookingId,
                'invoice_number' => $number,
                'year' => $year,
                'counter' => $next,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $number;
        });
    }

    /**
     * Read the current value of the counter for a given year without
     * advancing it. Intended for tests and admin diagnostics; production
     * code must always go through {@see self::getOrAssign()}.
     */
    public function currentCounter(int $year): int
    {
        $value = DB::table('invoice_counters')
            ->where('year', $year)
            ->value('counter');

        return (int) ($value ?? 0);
    }
}
