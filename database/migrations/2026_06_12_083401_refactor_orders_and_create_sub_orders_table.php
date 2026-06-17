<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove the single-store bottleneck from the Parent Order
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign('orders_store_id_foreign');
            $table->dropIndex('orders_status_store_id_index');
            $table->dropColumn('store_id');
        });

        // 2. Create the Child Order (Sub-Order) table for multi-vendor grouping
        Schema::create('sub_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();

            // Store-specific statuses
            $table->string('status')->default('pending_acceptance');

            // Financial splits for THIS specific store
            $table->bigInteger('subtotal_minor_unit');
            $table->bigInteger('platform_commission_minor_unit')->default(0);

            // Logistics
            $table->integer('estimated_prep_time_minutes')->nullable();

            $table->timestamps();

            // Indexes for fast driver/store queries
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        // 1. Drop the newly created table first to avoid constraint issues
        Schema::dropIfExists('sub_orders');

        // 2. Safely reconstruct the orders table to its original state
        Schema::table('orders', function (Blueprint $table) {
            // Create the column first (make it nullable if you have existing order data)
            $table->unsignedBigInteger('store_id')->nullable()->after('id');

            // Re-create the specific composite index you dropped
            $table->index(['status', 'store_id'], 'orders_status_store_id_index');

            // Re-apply the foreign key constraint
            $table->foreign('store_id')
                ->references('id')
                ->on('stores')
                ->onDelete('restrict');
        });
    }
};
