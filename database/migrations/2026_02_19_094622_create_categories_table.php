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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            // Prepojenie na užívateľa (Multi-tenancy)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Prepojenie na samú seba (Parent/Child vzťah)
            // nullable() znamená, že hlavná kategória nemusí mať nadradenú kategóriu.
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->string('name'); // Názov kategórie (napr. Potraviny)
            $table->string('type'); // Budeme tu ukladať 'income' (príjem) alebo 'expense' (výdavok)
            $table->string('icon')->nullable(); // Názov ikony (neskôr využijeme)
            $table->string('color')->nullable(); // HEX kód farby pre grafy (napr. #FF0000)

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
