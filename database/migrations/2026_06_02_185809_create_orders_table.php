<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->foreignId('customer_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('store_id')->constrained()->onDelete('restrict');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('restrict');

            $table->foreignId('user_address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('snapshot_delivery_address');
            $table->decimal('snapshot_delivery_latitude', 10, 8);
            $table->decimal('snapshot_delivery_longitude', 11, 8);

            // Pure Laravel Enums: No raw SQL required, indexes can be declared right away
            $table->enum('status', ['draft', 'pending_acceptance', 'accepted', 'preparing', 'ready_for_pickup', 'picked_up', 'in_transit', 'delivered', 'cancelled', 'failed'])->default('draft');
            $table->enum('payment_status', ['unpaid', 'paid', 'failed'])
                ->default('unpaid');

            $table->unsignedBigInteger('subtotal_minor_unit');
            $table->unsignedBigInteger('delivery_fee_minor_unit');
            $table->unsignedBigInteger('service_fee_minor_unit');
            $table->unsignedBigInteger('total_minor_unit');
            $table->char('currency_code', 3)->default('NGN');

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            // Standard Laravel performance indexes
            $table->index(['status', 'store_id']);
            $table->index(['status', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
