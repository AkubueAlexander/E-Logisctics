<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Core Delivery Mission Table
        Schema::create('delivery_missions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');

            // Mission lifecycle: pending (searching), assigned, picking_up, in_transit, completed, failed
            $table->string('status')->default('pending');

            // Snapshot tracking for the multi-vendor payload route
            $table->unsignedInteger('delivery_fee_minor_unit');
            $table->timestamps();
        });



    }

    public function down(): void
    {

        Schema::dropIfExists('delivery_missions');
    }
};
