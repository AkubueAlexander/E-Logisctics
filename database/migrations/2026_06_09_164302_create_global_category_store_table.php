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
        Schema::create('global_category_store', function (Blueprint $table) {
            $table->id();

            // Link to the global_categories table
            $table->foreignId('global_category_id')
                ->constrained('global_categories')
                ->onDelete('cascade');

            // Link to your newly refactored stores table
            $table->foreignId('store_id')
                ->constrained('stores')
                ->onDelete('cascade');

            $table->timestamps();

            // Prevent a store from being linked to the exact same global category row twice
            $table->unique(['global_category_id', 'store_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_category_store');
    }
};
