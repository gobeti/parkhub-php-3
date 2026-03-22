<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenants table
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('domain')->nullable()->unique();
            $table->json('branding')->nullable();
            $table->timestamps();
        });

        // Add iCal token to users
        Schema::table('users', function (Blueprint $table) {
            $table->string('ical_token', 48)->nullable()->unique()->after('notification_preferences');
        });

        // Add tenant_id to users, parking_lots, bookings
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('ical_token');
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::table('parking_lots', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['tenant_id', 'ical_token']);
        });

        Schema::dropIfExists('tenants');
    }
};
