<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_telemetry_breadcrumbs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
        });

        // Add the PostGIS Point column natively
        DB::statement('ALTER TABLE driver_telemetry_breadcrumbs ADD COLUMN coordinates geometry(Point, 4326)');

        // Add a spatial index for high-performance spatial querying later
        DB::statement('CREATE INDEX driver_breadcrumbs_coordinates_spatial_index ON driver_telemetry_breadcrumbs USING gist(coordinates)');
        // Add standard index for fast lookup by order
        DB::statement('CREATE INDEX driver_breadcrumbs_order_id_index ON driver_telemetry_breadcrumbs(order_id)');
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_telemetry_breadcrumbs');
    }
};
