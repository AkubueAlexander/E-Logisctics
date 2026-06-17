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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->unique()->nullable()->after('email');
            // Global scope isolation only
            $table->enum('system_role', [
                'customer',
                'store_manager',
                'store_representative',
                'driver',
                'admin',
            ])->default('customer')->after('phone_number');

            $table->boolean('is_active')->default(true)->after('system_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['phone_number', 'system_role', 'is_active']);
            });
        });
    }
};
