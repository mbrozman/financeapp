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
        Schema::table('financial_plan_items', function (Blueprint $table) {
            $table->foreignId('goal_id')->nullable()->constrained()->nullOnDelete();
            $table->dropColumn('is_reserve');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_plan_items', function (Blueprint $table) {
            $table->dropForeign(['goal_id']);
            $table->dropColumn('goal_id');
            $table->boolean('is_reserve')->default(false);
        });
    }
};
