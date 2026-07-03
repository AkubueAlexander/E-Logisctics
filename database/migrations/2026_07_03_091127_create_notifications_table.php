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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // Stores the class name: "App\Notifications\NewOrderReceived"
            $table->morphs('notifiable'); // Adds notifiable_type and notifiable_id columns
            $table->text('data'); // Stores the JSON payload from toArray()
            $table->timestamp('read_at')->nullable(); // Null if unread
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};