<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_incomes', function (Blueprint $table) {
            $table->id();
            if (config('database.default') === 'sqlite') {
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }
            
            // Suma výplaty
            $table->decimal('amount', 19, 4); 
            
            // Mesiac (napr. 2025-03)
            $table->string('period')->index(); 
            
            $table->string('note')->nullable();
            $table->timestamps();

            // Zabezpečíme, aby mal užívateľ len jeden hlavný príjem na mesiac
            $table->unique(['user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_incomes');
    }
};