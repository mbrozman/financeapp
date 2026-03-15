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
    Schema::table('budgets', function (Blueprint $table) {
        // Pridáme dátum platnosti (napr. 2025-03-01)
        $table->date('valid_from')->default(now()->startOfMonth())->index();
        
        // Stĺpec period (2025-03) môžeme vďaka tomu vymazať, lebo je už zbytočný
        // Najprv musíme odstrániť unikátny kľúč a indexy, inak to v SQLite padne
        $table->dropUnique(['user_id', 'category_id', 'period']);
        $table->dropIndex(['period']);
        $table->dropColumn('period'); 
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            //
        });
    }
};
