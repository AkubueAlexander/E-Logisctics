<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('driver_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('vehicle_type'); // bicycle, motorcycle, car
            $table->string('license_plate')->nullable();
            $table->enum('verification_status', [
                'pending',
                'verified',
                'rejected',
                'suspended'
            ])->default('pending');

            // operational availability
            $table->enum('availability_status', [
                'offline',
                'available',
                'busy'
            ])->default('offline');
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->timestamp('last_location_update')->nullable();
            $table->timestamps();

            $table->index(['availability_status', 'current_latitude', 'current_longitude'], 'driver_geo_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_profiles');
    }
};
