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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->onDelete('cascade');
            $table->string('currency', 3); // ISO 4217 currency code (e.g., EGP, SAR, USD)
            $table->unsignedBigInteger('balance')->default(0); // Store in smallest unit (piastres, halalas, cents, etc.)
            $table->string('type')->default('main'); // main, savings, etc. for future extensibility
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['client_id', 'currency', 'type']);
            $table->index(['client_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
