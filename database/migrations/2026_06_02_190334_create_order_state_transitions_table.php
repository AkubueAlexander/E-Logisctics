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
        // Now you don't even need the dropIfExists guard here anymore!
        Schema::create('order_state_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            $table->string('from_status')->nullable();
            $table->string('to_status');

            // Who triggered the state change?
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_state_transitions');
    }
};
