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
        Schema::table('investment_transactions', function (Blueprint $table) {
        $table->dropColumn('currency'); // Vymažeme starý textový stĺpec
        $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investment_transactions', function (Blueprint $table) {
            //
        });
    }
};
