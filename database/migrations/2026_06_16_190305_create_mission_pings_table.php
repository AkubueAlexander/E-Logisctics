<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {


        // 1. Individual Driver 30-Second Ping Records
        Schema::create('mission_pings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_mission_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('users')->onDelete('cascade');

            // Ping status: sent, accepted, rejected, timed_out
            $table->string('status')->default('sent');
            $table->timestamp('expires_at');
            $table->timestamps();

            // Index for high-performance timeout checking cron jobs/workers
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_pings');

    }
};
