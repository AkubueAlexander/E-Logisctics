<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Drop old constraint cleanly inside schema context
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');

            // Apply updated status array list
            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN (
                'draft', 'pending_payment', 'pending_acceptance', 'accepted',
                'preparing', 'ready_for_pickup', 'picked_up', 'in_transit',
                'delivered', 'cancelled', 'failed'
            ))");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Revert changes back on rollbacks
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check');

            DB::statement("ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (status IN (
                'draft', 'pending_acceptance', 'accepted',
                'preparing', 'ready_for_pickup', 'picked_up', 'in_transit',
                'delivered', 'cancelled', 'failed'
            ))");
        });
    }
};
