<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency log for processed Stripe webhook events.
 *
 * Stripe delivers events at-least-once, so a retry of
 * `checkout.session.completed` would otherwise double-credit the
 * customer. The `event_id` column is the dedup key: attempting to
 * insert a duplicate raises a UniqueConstraintViolationException
 * which StripeWebhookService converts into an "already processed"
 * 200 OK response (Stripe treats any 2xx as an acknowledgement).
 *
 * The insert is performed inside the same transaction as the credit
 * grant so a crash between the two cannot leave the ledger
 * inconsistent. Retention policy is intentionally not set yet —
 * the table grows unbounded until a dedicated follow-up trims it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stripe_events', function (Blueprint $table) {
            $table->string('event_id')->primary();
            $table->string('type');
            $table->timestamp('received_at')->useCurrent();
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_events');
    }
};
