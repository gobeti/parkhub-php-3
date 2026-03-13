<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('credits_balance')->default(0)->after('department');
            $table->integer('credits_monthly_quota')->default(10)->after('credits_balance');
            $table->timestamp('credits_last_refilled')->nullable()->after('credits_monthly_quota');
        });

        Schema::create('credit_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('booking_id')->nullable();
            $table->integer('amount');
            $table->string('type'); // grant, deduction, refund, monthly_refill
            $table->string('description')->nullable();
            $table->uuid('granted_by')->nullable(); // admin who granted
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_transactions');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['credits_balance', 'credits_monthly_quota', 'credits_last_refilled']);
        });
    }
};
