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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            
            // Explicitly foreign key to the wallets table
            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->onDelete('cascade');
                
            $table->string('type', 20); // 'credit' or 'debit'
            $table->bigInteger('amount_minor_unit'); // Integer values for precise cash tracking
            
            // CRITICAL FINTECH RULE: Keep a snapshot of what the balance was immediately after this transaction line
            $table->bigInteger('running_balance'); 
            
            $table->text('description');
            
            // Unique reference/fingerprint column for high-speed idempotency screening
            $table->string('reference')->nullable(); 
            
            $table->timestamps();

            // High performance composite index for loading history lookups safely
            $table->index(['wallet_id', 'created_at']);
            $table->unique(['wallet_id', 'reference']); // Strict safety guard against duplicate processing
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};