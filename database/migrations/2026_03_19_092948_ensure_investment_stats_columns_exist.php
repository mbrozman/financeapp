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
            // 1. Zabezpečíme total_quantity
            if (!Schema::hasColumn('investments', 'total_quantity')) {
                // Ak náhodou existuje stará 'quantity', premenujme ju, inak pridáme novú
                if (Schema::hasColumn('investments', 'quantity')) {
                    $table->renameColumn('quantity', 'total_quantity');
                } else {
                    $table->decimal('total_quantity', 19, 8)->default(0)->after('name');
                }
            }

            // 2. Zabezpečíme average_buy_price
            if (!Schema::hasColumn('investments', 'average_buy_price')) {
                $table->decimal('average_buy_price', 19, 4)->default(0)->after('total_quantity');
            }
            
            // 3. Ostatné polia
            if (!Schema::hasColumn('investments', 'average_buy_price_eur')) {
                $table->decimal('average_buy_price_eur', 19, 4)->default(0)->after('average_buy_price');
            }
            if (!Schema::hasColumn('investments', 'total_invested_base')) {
                $table->decimal('total_invested_base', 19, 4)->default(0)->after('average_buy_price_eur');
            }
            if (!Schema::hasColumn('investments', 'total_invested_eur')) {
                $table->decimal('total_invested_eur', 19, 4)->default(0)->after('total_invested_base');
            }
            if (!Schema::hasColumn('investments', 'total_sales_base')) {
                $table->decimal('total_sales_base', 19, 4)->default(0)->after('total_invested_eur');
            }
            if (!Schema::hasColumn('investments', 'total_sales_eur')) {
                $table->decimal('total_sales_eur', 19, 4)->default(0)->after('total_sales_base');
            }
            if (!Schema::hasColumn('investments', 'total_dividends_base')) {
                $table->decimal('total_dividends_base', 19, 4)->default(0)->after('total_sales_eur');
            }
            if (!Schema::hasColumn('investments', 'realized_gain_base')) {
                $table->decimal('realized_gain_base', 19, 4)->default(0)->after('total_dividends_base');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
             $cols = [
                'total_quantity',
                'average_buy_price',
                'average_buy_price_eur',
                'total_invested_base',
                'total_invested_eur',
                'total_sales_base',
                'total_sales_eur',
                'total_dividends_base',
                'realized_gain_base'
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('investments', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
