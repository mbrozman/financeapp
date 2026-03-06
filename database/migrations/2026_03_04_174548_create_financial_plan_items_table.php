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
        Schema::create('financial_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Napr. "Investície"
            $table->decimal('percentage', 5, 2); // Napr. 25.00

            // Dôležité: Má sa na tento šuflík vzťahovať ten 8% výnos? 
            // (Áno pre investície, nie pre hotovosť v šuflíku)
            $table->boolean('applies_expected_return')->default(false);

            // Má sa táto kategória počítať do rastu majetku?
            // (Áno pre Investície a Rezervu, nie pre Nájom a Fun Money)
            $table->boolean('contributes_to_net_worth')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_plan_items');
    }
};
