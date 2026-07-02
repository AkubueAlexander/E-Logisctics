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
        Schema::table('wallets', function (Blueprint $table) {
            // 1. Drop the standard index created by $table->morphs('owner')
            $table->dropIndex(['owner_type', 'owner_id']);
            
            // 2. Add the unique index required by uniqueMorphs
            $table->unique(['owner_type', 'owner_id'], 'wallets_owner_type_owner_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            // 1. Drop the unique index
            $table->dropUnique('wallets_owner_type_owner_id_unique');
            
            // 2. Restore the original standard index
            $table->index(['owner_type', 'owner_id']);
        });
    }
};