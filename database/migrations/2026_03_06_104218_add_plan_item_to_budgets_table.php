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
        // Pridáme prepojenie na finančný pilier (šuflík)
        // nullable() pridávame pre prípad, že už v tabuľke nejaké dáta máš
        $table->foreignId('financial_plan_item_id')->nullable()->constrained()->cascadeOnDelete();
    });
}

public function down(): void
{
    Schema::table('budgets', function (Blueprint $table) {
        $table->dropForeign(['financial_plan_item_id']);
        $table->dropColumn('financial_plan_item_id');
    });
}
};
