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
        $table->decimal('daily_change_percentage', 8, 2)->nullable(); // napr. +2.50%
        $table->timestamp('last_price_update')->nullable();
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
