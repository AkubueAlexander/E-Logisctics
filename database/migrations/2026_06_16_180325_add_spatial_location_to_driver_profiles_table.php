<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enable the PostGIS extension in PostgreSQL
        DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');

        Schema::table('driver_profiles', function (Blueprint $blueprint) {
            // 2. Add a spatial geometry column for coordinates (SRID 4326 represents standard GPS coordinates)
            DB::statement('ALTER TABLE driver_profiles ADD COLUMN location GEOMETRY(Point, 4326);');

            // 3. Add a spatial index (GIST) so 5KM calculations happen in milliseconds
            DB::statement('CREATE INDEX driver_profiles_location_gist ON driver_profiles USING gist(location);');
        });
    }

    public function down(): void
    {
        Schema::table('driver_profiles', function (Blueprint $blueprint) {
            DB::statement('DROP INDEX IF EXISTS driver_profiles_location_gist;');
            DB::statement('ALTER TABLE driver_profiles DROP COLUMN IF EXISTS location;');
        });
    }
};
