<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add coordinates to parking lots for map view
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        // Stripe payment records
        Schema::create('stripe_payments', function (Blueprint $table) {
            $table->string('id')->primary(); // Stripe session ID or stub ID
            $table->uuid('user_id');
            $table->integer('amount'); // cents
            $table->integer('credits');
            $table->string('currency', 3)->default('eur');
            $table->string('status')->default('pending'); // pending, completed, expired, failed
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_payments');

        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
