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
        // Spustíme synchronizáciu štatistík pre všetky investície
        // Toto zabezpečí, že po pridaní stĺpcov sa v nich hneď objavia správne dáta aj na produkcii
        \App\Models\Investment::all()->each(function ($inv) {
            \App\Services\InvestmentCalculationService::refreshStats($inv);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
