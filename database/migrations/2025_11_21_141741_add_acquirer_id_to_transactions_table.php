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
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('acquirer_id')->nullable()->after('wallet_id')->constrained('acquirers')->onDelete('restrict');
            $table->dropUnique('unique_transaction_per_bank');

            $table->unique(['reference', 'acquirer_id'], 'unique_transaction_per_acquirer');
            $table->index('acquirer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('unique_transaction_per_acquirer');
            $table->unique(['reference', 'source'], 'unique_transaction_per_bank');

            $table->dropForeign(['acquirer_id']);
            $table->dropColumn('acquirer_id');
        });
    }
};
