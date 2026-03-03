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
        Schema::create('investment_price_histories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
        $table->decimal('price', 19, 4);
        $table->date('recorded_at')->index(); // Dátum, ku ktorému cena patrí
        $table->timestamps();

        // Zabezpečíme, aby sme pre jednu investíciu nemali dva záznamy v ten istý deň
        $table->unique(['investment_id', 'recorded_at']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_price_histories');
    }
};
