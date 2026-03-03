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
        Schema::table('investments', function (Blueprint $table) {
        // Odstránime starý textový stĺpec a pridáme prepojenie na tabuľku currencies
        $table->dropColumn('base_currency'); 
        $table->foreignId('currency_id')->nullable()->constrained('currencies')->nullOnDelete();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            //
        });
    }
};
