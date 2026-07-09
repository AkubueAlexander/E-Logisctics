<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            // 1. Add the spatial geometry column (Point type, SRID 4326 for WGS 84 GPS coordinates)
            $table->geometry('location', 'point', 4326)->nullable();
        });

        // 2. Create a high-performance GiST spatial index
        DB::statement('CREATE INDEX stores_location_gist ON stores USING gist (location);');

        // 3. Automatically backfill the new location column using your existing lat/long data
        // Remember: PostGIS ST_MakePoint takes (longitude, latitude)
        DB::statement('
            UPDATE stores
            SET location = ST_SetSRID(ST_MakePoint(longitude, latitude), 4326)
            WHERE longitude IS NOT NULL AND latitude IS NOT NULL;
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop the spatial index first
        DB::statement('DROP INDEX IF EXISTS stores_location_gist;');

        // 2. Drop the column
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('location');
        });
    }
};
