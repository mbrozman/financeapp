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
        // Odstránime starý textový stĺpec, ak tam ešte je
        if (Schema::hasColumn('investment_transactions', 'currency')) {
            $table->dropColumn('currency');
        }
        
        // Pridáme korektné prepojenie na tabuľku currencies
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
