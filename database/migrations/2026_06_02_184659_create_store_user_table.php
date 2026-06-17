<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Pure Laravel Enum: Portable across Postgres, MySQL, and SQLite
            $table->enum('role', ['owner', 'manager', 'staff'])->default('staff');

            $table->timestamps();

            // The unique constraint works out of the box now
            $table->unique(['store_id', 'user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_user');
        DB::statement("DROP TYPE IF EXISTS store_role_enum");
    }
};
