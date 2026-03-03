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
        // 1. Ak tam stĺpec currency existuje, zmažeme ho
        if (Schema::hasColumn('investment_transactions', 'currency')) {
            $table->dropColumn('currency');
        }
        
        // 2. Pridáme korektné currency_id
        if (!Schema::hasColumn('investment_transactions', 'currency_id')) {
            $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
        }
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
