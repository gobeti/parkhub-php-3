<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Visitors table
        Schema::create('visitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('host_user_id');
            $table->string('name');
            $table->string('email');
            $table->string('vehicle_plate')->nullable();
            $table->timestamp('visit_date');
            $table->string('purpose')->nullable();
            $table->string('status')->default('pending'); // pending, checked_in, expired, cancelled
            $table->text('qr_code')->nullable();
            $table->string('pass_url')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->foreign('host_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['host_user_id', 'status']);
            $table->index('visit_date');
        });

        // EV Chargers table
        Schema::create('ev_chargers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lot_id');
            $table->string('label');
            $table->string('connector_type'); // type2, ccs, chademo, tesla
            $table->float('power_kw');
            $table->string('status')->default('available'); // available, in_use, offline, maintenance
            $table->string('location_hint')->nullable();
            $table->timestamps();

            $table->foreign('lot_id')->references('id')->on('parking_lots')->onDelete('cascade');
            $table->index(['lot_id', 'status']);
        });

        // Charging sessions table
        Schema::create('charging_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('charger_id');
            $table->uuid('user_id');
            $table->timestamp('start_time');
            $table->timestamp('end_time')->nullable();
            $table->float('kwh_consumed')->default(0);
            $table->string('status')->default('active'); // active, completed, cancelled
            $table->timestamps();

            $table->foreign('charger_id')->references('id')->on('ev_chargers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['charger_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charging_sessions');
        Schema::dropIfExists('ev_chargers');
        Schema::dropIfExists('visitors');
    }
};
