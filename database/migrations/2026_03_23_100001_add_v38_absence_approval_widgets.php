<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add reviewer_comment to absences table for approval workflow
        if (Schema::hasTable('absences') && ! Schema::hasColumn('absences', 'reviewer_comment')) {
            Schema::table('absences', function (Blueprint $table) {
                $table->text('reviewer_comment')->nullable()->after('reviewed_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('absences') && Schema::hasColumn('absences', 'reviewer_comment')) {
            Schema::table('absences', function (Blueprint $table) {
                $table->dropColumn('reviewer_comment');
            });
        }
    }
};
