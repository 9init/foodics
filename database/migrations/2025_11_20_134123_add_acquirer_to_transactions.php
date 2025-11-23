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
            $table->foreignId('acquirer_id')->nullable()->after('id')->constrained()->onDelete('restrict');

            $table->index(['acquirer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['acquirer_id', 'created_at']);
            $table->dropForeign(['acquirer_id']);
            $table->dropColumn(['acquirer_id', 'currency']);
        });
    }
};
