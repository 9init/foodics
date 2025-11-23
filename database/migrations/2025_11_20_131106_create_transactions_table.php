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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('reference'); // Bank reference
            $table->string('type'); // credit, debit
            $table->unsignedBigInteger('amount'); // Stored in smallest unit
            $table->string('currency', 3); // ISO 4217
            $table->string('source'); // foodics_bank, acme_bank, internal, etc.
            $table->json('metadata')->nullable(); // Store parsed key-value pairs from webhook
            $table->timestamp('transaction_date');
            $table->string('status')->default('completed'); // completed, pending, failed
            $table->timestamps();

            $table->unique(['reference', 'source'], 'unique_transaction_per_bank');
            $table->index(['wallet_id', 'created_at']);
            $table->index('transaction_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
