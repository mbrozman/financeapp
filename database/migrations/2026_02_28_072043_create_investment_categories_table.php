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
         Schema::create('investment_categories', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        $table->string('name'); // napr. ETF, Akcie, Kryptomeny, Zlato
        $table->string('slug'); // automatické meno pre URL
        $table->string('icon')->nullable(); // heroicon-o-chart-pie
        $table->string('color')->default('#3b82f6'); // Farba pre grafy
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_categories');
    }
};
