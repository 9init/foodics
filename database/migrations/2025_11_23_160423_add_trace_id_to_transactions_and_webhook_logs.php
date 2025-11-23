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
            $table->string('trace_id', 150)->nullable()->index()->after('id');
        });

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->string('trace_id', 150)->nullable()->index()->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('trace_id');
        });

        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn('trace_id');
        });
    }
};
