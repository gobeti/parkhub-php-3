<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->decimal('hourly_rate', 8, 2)->nullable()->after('status');
            $table->decimal('daily_max', 8, 2)->nullable()->after('hourly_rate');
            $table->decimal('monthly_pass', 8, 2)->nullable()->after('daily_max');
            $table->string('currency')->default('EUR')->after('monthly_pass');
        });
    }

    public function down(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropColumn(['hourly_rate', 'daily_max', 'monthly_pass', 'currency']);
        });
    }
};
