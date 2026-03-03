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
        // Tieto stĺpce už nepotrebujeme, všetko budeme počítať z transakcií
        $table->dropColumn(['quantity', 'average_buy_price']);
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
        $table->decimal('quantity', 19, 8)->default(0);
        $table->decimal('average_buy_price', 19, 4)->default(0);
    });
    }
};
