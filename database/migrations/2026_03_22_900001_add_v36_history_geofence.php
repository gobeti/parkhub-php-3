<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            if (! Schema::hasColumn('parking_lots', 'center_lat')) {
                $table->decimal('center_lat', 10, 7)->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('parking_lots', 'center_lng')) {
                $table->decimal('center_lng', 10, 7)->nullable()->after('center_lat');
            }
            if (! Schema::hasColumn('parking_lots', 'geofence_radius_m')) {
                $table->unsignedInteger('geofence_radius_m')->default(100)->after('center_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parking_lots', function (Blueprint $table) {
            $table->dropColumn(['center_lat', 'center_lng', 'geofence_radius_m']);
        });
    }
};
