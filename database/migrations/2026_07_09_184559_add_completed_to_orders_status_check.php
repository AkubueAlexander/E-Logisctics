<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop the old check constraint
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');

        // 2. Re-create the constraint with 'completed' included
        DB::statement("
            ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (
                (status)::text = ANY (ARRAY[
                    'draft'::character varying,
                    'pending_payment'::character varying,
                    'pending_acceptance'::character varying,
                    'accepted'::character varying,
                    'searching_for_driver'::character varying,
                    'driver_assigned'::character varying,
                    'preparing'::character varying,
                    'ready_for_pickup'::character varying,
                    'picked_up'::character varying,
                    'in_transit'::character varying,
                    'delivered'::character varying,
                    'completed'::character varying, -- Added new completed status
                    'cancelled'::character varying,
                    'failed'::character varying
                ]::text[])
            );
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop the modified constraint
        DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_status_check;');

        // 2. Re-create the constraint back to the previous state (without 'completed')
        DB::statement("
            ALTER TABLE orders ADD CONSTRAINT orders_status_check CHECK (
                (status)::text = ANY (ARRAY[
                    'draft'::character varying,
                    'pending_payment'::character varying,
                    'pending_acceptance'::character varying,
                    'accepted'::character varying,
                    'searching_for_driver'::character varying,
                    'driver_assigned'::character varying,
                    'preparing'::character varying,
                    'ready_for_pickup'::character varying,
                    'picked_up'::character varying,
                    'in_transit'::character varying,
                    'delivered'::character varying,
                    'cancelled'::character varying,
                    'failed'::character varying
                ]::text[])
            );
        ");
    }
};
