<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend audit_log with event_type, target fields
        Schema::table('audit_log', function (Blueprint $table) {
            $table->string('event_type')->nullable()->after('action');
            $table->string('target_type')->nullable()->after('ip_address');
            $table->string('target_id')->nullable()->after('target_type');
            $table->index('event_type');
        });

        // Extend vehicles with fleet management fields
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('vehicle_type')->default('car')->after('color');
            $table->string('license_plate')->nullable()->after('plate');
            $table->boolean('flagged')->default(false)->after('photo_url');
            $table->string('flag_reason')->nullable()->after('flagged');
            $table->index('vehicle_type');
        });
    }

    public function down(): void
    {
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex(['event_type']);
            $table->dropColumn(['event_type', 'target_type', 'target_id']);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropIndex(['vehicle_type']);
            $table->dropColumn(['vehicle_type', 'license_plate', 'flagged', 'flag_reason']);
        });
    }
};
