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
        Schema::rename('portfolio_snapshots', 'net_worth_snapshots');
        Schema::table('net_worth_snapshots', function (Blueprint $table) {
            $table->decimal('total_liquid_cash_eur', 19, 4)->default(0)->after('total_invested_eur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('net_worth_snapshots', function (Blueprint $table) {
            //
        });
    }
};
