<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('monthly_credit_limit')
                  ->default(40)
                  ->after('role');  // ← change 'role' to 'email' or last column if 'role' doesn't exist

            $table->unsignedInteger('monthly_credits_used')
                  ->default(0)
                  ->after('monthly_credit_limit');

            $table->timestamp('credits_reset_at')
                  ->nullable()
                  ->after('monthly_credits_used');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['monthly_credit_limit', 'monthly_credits_used', 'credits_reset_at']);
        });
    }
};
