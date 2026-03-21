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
        Schema::create('investments', function (Blueprint $table) {
        $table->id();
        // Multi-tenancy
        if (config('database.default') === 'sqlite') {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
        } else {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        }
        
        // Prepojenie na investičný účet (z Modulu 2)
        $table->foreignId('account_id')->constrained()->cascadeOnDelete();

        $table->string('ticker'); // Napr. AAPL, VWCE.DE, BTC
        $table->string('name');   // Napr. Apple Inc.
        
        // Počet kusov: 19 číslic celkom, 8 desatinných (napr. 0.005 BTC)
        $table->decimal('quantity', 19, 8)->default(0);
        
        // Priemerná nákupná cena (za 1 kus)
        $table->decimal('average_buy_price', 19, 4)->default(0);
        
        // Aktuálna trhová cena (túto budeme neskôr aktualizovať cez API)
        $table->decimal('current_price', 19, 4)->default(0);

        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
