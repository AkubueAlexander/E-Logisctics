<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_order_id')->constrained('sub_orders')->cascadeOnDelete();

            // We use nullable nullOnDelete so if a store deletes a menu item, historical receipts don't break
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            // Snapshot fields (CRITICAL: Never rely solely on relations for historical pricing/names)
            $table->string('product_name');
            $table->integer('quantity');
            $table->bigInteger('unit_price_minor_unit');
            $table->bigInteger('total_price_minor_unit');

            // For things like "Extra Cheese" or "No Onions"
            $table->jsonb('customizations')->nullable();
            $table->text('special_instructions')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
