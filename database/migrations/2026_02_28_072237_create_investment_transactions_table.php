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
        Schema::create('investment_transactions', function (Blueprint $table) {
        $table->id();
        if (config('database.default') === 'sqlite') {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        } else {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        }
        $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
        
        // Typ pohybu: buy (nákup), sell (predaj), dividend (dividenda)
        $table->string('type'); 
        
        $table->decimal('quantity', 19, 8); // Koľko kusov sme kúpili/predali
        $table->decimal('price_per_unit', 19, 4); // Cena za 1 kus v mene nákupu (napr. USD)
        
        // Poplatky (brokerovi) - dôležité pre presný výnos
        $table->decimal('commission', 19, 4)->default(0); 
        
        // Mena a kurz v čase nákupu (aby sme vedeli prepočítať na EUR)
        $table->string('currency', 3)->default('USD');
        $table->decimal('exchange_rate', 19, 8)->default(1.0); 
        
        $table->date('transaction_date')->index(); // Dátum nákupu (kľúčové pre daňový test!)
        $table->text('notes')->nullable();
        
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_transactions');
    }
};
