<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add the columns to the orders table
        Schema::table('orders', function (Blueprint $table) {

            // PostGIS geometry column using point subtype and SRID 4326
            $table->geometry('location', 'point', 4326)->nullable()->after('longitude');
        });

        // 2. Backfill all existing records with your specific test coordinates
        DB::statement("
            UPDATE public.orders
            SET
                location = ST_SetSRID(ST_MakePoint(7.50170800, 6.44440000), 4326)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['location']);
        });
    }
};
