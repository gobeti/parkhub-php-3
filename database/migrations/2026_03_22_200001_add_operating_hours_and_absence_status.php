<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->json('operating_hours')->nullable()->after('currency');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->string('status')->default('approved')->after('source');
            $table->uuid('reviewed_by')->nullable()->after('status');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropColumn('operating_hours');
        });

        Schema::table('absences', function (Blueprint $table) {
            $table->dropColumn(['status', 'reviewed_by', 'reviewed_at']);
        });
    }
};
