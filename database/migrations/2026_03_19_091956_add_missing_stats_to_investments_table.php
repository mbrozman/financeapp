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
            $table->decimal('total_dividends_base', 19, 4)->default(0)->after('realized_gain_base');
            $table->decimal('total_invested_eur', 19, 4)->default(0)->after('total_dividends_base');
            $table->decimal('total_sales_eur', 19, 4)->default(0)->after('total_invested_eur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['total_dividends_base', 'total_invested_eur', 'total_sales_eur']);
        });
    }
};
