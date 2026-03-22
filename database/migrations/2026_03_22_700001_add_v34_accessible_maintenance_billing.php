<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add is_accessible to parking_slots
        Schema::table('parking_slots', function (Blueprint $table) {
            $table->boolean('is_accessible')->default(false)->after('features');
        });

        // Add accessibility_needs, cost_center, department to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('accessibility_needs')->default('none')->after('department');
            $table->string('cost_center')->nullable()->after('accessibility_needs');
        });

        // Create maintenance_windows table
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lot_id');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->string('reason');
            $table->json('affected_slots')->nullable();
            $table->timestamps();

            $table->foreign('lot_id')->references('id')->on('parking_lots')->onDelete('cascade');
            $table->index(['start_time', 'end_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['accessibility_needs', 'cost_center']);
        });

        Schema::table('parking_slots', function (Blueprint $table) {
            $table->dropColumn('is_accessible');
        });
    }
};
