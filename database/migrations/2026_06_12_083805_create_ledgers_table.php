<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();

            // Link to the parent order, but NEVER cascade delete finances
            $table->foreignId('order_id')->nullable()->constrained('orders')->restrictOnDelete();

            // Link to the specific multi-vendor sub-order
            $table->foreignId('sub_order_id')->nullable()->constrained('sub_orders')->restrictOnDelete();

            // Type of ledger entry: 'customer_charge', 'vendor_payout', 'driver_payout', 'platform_revenue'
            $table->string('transaction_type');

            // Polymorphic-ish relations depending on who is getting paid
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->bigInteger('amount_minor_unit');
            $table->char('currency_code', 3)->default('NGN'); // Great local default

            // 'pending' (in escrow), 'cleared' (withdrawable), 'failed'
            $table->string('status')->default('pending');

            $table->timestamps();

            $table->index('order_id');
            $table->index('sub_order_id');

            // Indexes
            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledgers');
    }
};
