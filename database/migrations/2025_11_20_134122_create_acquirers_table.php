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
        Schema::create('acquirers', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Foodics Bank", "Acme Bank"
            $table->string('identifier')->unique(); // e.g., "foodics_bank", "acme_bank"
            $table->string('parser_class'); // Fully qualified parser class name
            $table->string('country_code', 2)->nullable(); // ISO 3166-1 alpha-2
            $table->string('currency', 3)->nullable(); // ISO 4217 currency code
            $table->string('webhook_endpoint')->nullable(); // Our endpoint for this acquirer
            $table->string('api_key')->nullable(); // For webhook authentication
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional configuration
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('identifier');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acquirers');
    }
};
