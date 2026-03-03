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
        $table->foreignId('investment_category_id')->nullable()->constrained()->nullOnDelete();
        $table->string('broker')->nullable(); // XTB, IBKR, Binance
        $table->string('base_currency', 3)->default('USD'); // Mena, v ktorej sa akcia reálne obchoduje
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            //
        });
    }
};
