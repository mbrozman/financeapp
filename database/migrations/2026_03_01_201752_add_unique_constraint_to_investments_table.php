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
        // Vytvoríme unikátny kľúč pre trojicu stĺpcov
        $table->unique(['user_id', 'ticker', 'broker'], 'user_ticker_broker_unique');
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
