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
    Schema::create('budgets', function (Blueprint $table) {
        $table->id();
        if (config('database.default') === 'sqlite') {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        } else {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        }
        
        // Na ktorú kategóriu (napr. Potraviny) je tento limit
        $table->foreignId('category_id')->constrained()->cascadeOnDelete();

        // Maximálna suma, ktorú chceš minúť
        $table->decimal('limit_amount', 19, 4);

        // Obdobie vo formáte "YYYY-MM" (napr. "2025-03")
        // Toto nám umožní ľahko filtrovať dáta pre konkrétny mesiac
        $table->string('period')->index(); 

        $table->timestamps();
        
        // Poistka: Užívateľ nemôže mať dva limity pre rovnakú kategóriu v tom istom mesiaci
        $table->unique(['user_id', 'category_id', 'period']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
