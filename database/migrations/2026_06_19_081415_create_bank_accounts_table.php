<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            // Explicit relations for ironclad foreign key integrity in Postgres
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('bank_code', 10);
            $table->string('bank_name');
            $table->string('account_number', 15);
            $table->string('account_name'); // Verified name returned from NIBSS via Flutterwave
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            // Indexes for lightning fast lookups
            $table->index(['user_id', 'is_primary']);
            $table->index(['store_id', 'is_primary']);
        });

        // Constraint to ensure a bank account belongs to EITHER a user or a store, never both, never neither.
        DB::statement('ALTER TABLE bank_accounts ADD CONSTRAINT chk_bank_account_owner CHECK (
            (user_id IS NOT NULL AND store_id IS NULL) OR
            (user_id IS NULL AND store_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
