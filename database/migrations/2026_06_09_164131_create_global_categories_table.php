<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., "Organic Veggies"
            $table->string('slug')->unique(); // e.g., "organic-veggies"
            $table->string('icon_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_categories');
    }
};
