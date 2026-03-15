<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('base_price', 8, 2)->nullable()->after('checked_in_at');
            $table->decimal('tax_amount', 8, 2)->nullable()->after('base_price');
            $table->decimal('total_price', 8, 2)->nullable()->after('tax_amount');
            $table->string('currency')->default('EUR')->after('total_price');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'tax_amount', 'total_price', 'currency']);
        });
    }
};
