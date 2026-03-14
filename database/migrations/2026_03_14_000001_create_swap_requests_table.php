<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swap_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('requester_booking_id');
            $table->uuid('target_booking_id');
            $table->uuid('requester_id');
            $table->uuid('target_id');
            $table->string('status')->default('pending'); // pending, accepted, declined, cancelled
            $table->text('message')->nullable();
            $table->timestamps();

            $table->foreign('requester_booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('target_booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['target_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swap_requests');
    }
};
