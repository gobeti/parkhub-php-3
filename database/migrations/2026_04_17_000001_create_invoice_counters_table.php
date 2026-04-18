<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated counter table for fortlaufende (sequentially ascending, gap-free)
 * invoice numbers required by German § 14 UStG.
 *
 * One row per fiscal year; the `counter` column is advanced inside a
 * SELECT ... FOR UPDATE / UPDATE transaction by
 * App\Services\Compliance\InvoiceNumberService.
 *
 * Policy: **per-year reset.** The counter restarts at 1 on January 1.
 * This is explicitly permitted by § 14 UStG because the year-segment of
 * the rendered invoice number (format `YYYY-NNNNNNN`) keeps the combined
 * identifier globally unique within the tax subject, which is what the
 * statute actually requires. The alternative (monotonic across years) is
 * equally compliant but yields ever-longer numbers; yearly reset is
 * preferred here for operator UX.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_counters', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('counter')->default(0);
            $table->timestamps();
        });

        // Mapping table: booking_id → issued invoice number. Populated on the
        // first PDF/HTML render for a booking; subsequent renders look up the
        // existing mapping rather than burning a fresh counter entry. This is
        // the idempotence half of the compliance invariant — without it, each
        // re-download would create a gap in the sequence.
        Schema::create('booking_invoice_numbers', function (Blueprint $table) {
            $table->uuid('booking_id')->primary();
            $table->string('invoice_number', 32)->unique();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedBigInteger('counter');
            $table->timestamps();

            $table->foreign('booking_id')
                ->references('id')
                ->on('bookings')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_invoice_numbers');
        Schema::dropIfExists('invoice_counters');
    }
};
