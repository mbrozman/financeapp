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
    Schema::table('budgets', function (Blueprint $table) {
        // Prepojíme Rozpočet (napr. Strava) so Šuflíkom (napr. Base)
        $table->foreignId('financial_plan_item_id')->nullable()->constrained()->nullOnDelete();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            //
        });
    }
};
