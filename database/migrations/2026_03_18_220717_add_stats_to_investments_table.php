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
            // Premenujeme staré quantity na total_quantity pre konzistenciu s kódom
            if (Schema::hasColumn('investments', 'quantity') && !Schema::hasColumn('investments', 'total_quantity')) {
                $table->renameColumn('quantity', 'total_quantity');
            }
            
            // Pridáme zvyšné štatistické polia (idempotentne)
            if (!Schema::hasColumn('investments', 'average_buy_price_eur')) {
                $table->decimal('average_buy_price_eur', 19, 4)->default(0)->after('average_buy_price');
            }
            if (!Schema::hasColumn('investments', 'total_invested_base')) {
                $table->decimal('total_invested_base', 19, 4)->default(0)->after('average_buy_price_eur');
            }
            if (!Schema::hasColumn('investments', 'total_sales_base')) {
                $table->decimal('total_sales_base', 19, 4)->default(0)->after('total_invested_base');
            }
            if (!Schema::hasColumn('investments', 'realized_gain_base')) {
                $table->decimal('realized_gain_base', 19, 4)->default(0)->after('total_sales_base');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn([
                'average_buy_price_eur',
                'total_invested_base',
                'total_sales_base',
                'realized_gain_base'
            ]);
            
            if (Schema::hasColumn('investments', 'total_quantity')) {
                $table->renameColumn('total_quantity', 'quantity');
            }
        });
    }
};
