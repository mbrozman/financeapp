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
        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Ukladáme dve kľúčové hodnoty pre grafy
            $table->decimal('total_invested_eur', 19, 4);   // Koľko si tam vtedy mal vložené
            $table->decimal('total_market_value_eur', 19, 4); // Akú to malo vtedy trhovú hodnotu

            $table->date('recorded_at')->index(); // Deň, ku ktorému snímka patrí

            $table->timestamps();

            // Jeden užívateľ = jedna snímka za deň
            $table->unique(['user_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
    }
};
