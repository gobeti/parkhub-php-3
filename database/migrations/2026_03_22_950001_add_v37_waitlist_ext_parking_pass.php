<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Enhanced waitlist fields
        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->unsignedTinyInteger('priority')->default(3)->after('slot_id');
            $table->string('status', 20)->default('waiting')->after('priority');
            $table->timestamp('offer_expires_at')->nullable()->after('status');
            $table->foreignUuid('accepted_booking_id')->nullable()->after('offer_expires_at');
        });

        // Parking passes table
        Schema::create('parking_passes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('verification_code', 32)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_passes');

        Schema::table('waitlist_entries', function (Blueprint $table) {
            $table->dropColumn(['priority', 'status', 'offer_expires_at', 'accepted_booking_id']);
        });
    }
};
