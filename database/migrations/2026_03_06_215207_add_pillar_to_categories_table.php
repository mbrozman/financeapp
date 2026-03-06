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
    Schema::table('categories', function (Blueprint $table) {
        // Prepojíme kategóriu (napr. Potraviny) s Pilierom (napr. Nevyhnutné výdavky)
        $table->foreignId('financial_plan_item_id')->nullable()->constrained()->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('categories', function (Blueprint $table) {
        $table->dropForeign(['financial_plan_item_id']);
        $table->dropColumn('financial_plan_item_id');
    });
}
};
