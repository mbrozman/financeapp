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
        Schema::create('budget_definitions', function (Blueprint $table) {
            $table->id();
            if (config('database.default') === 'sqlite') {
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_plan_item_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 19, 4);

            // Dátum, od ktorého toto pravidlo platí (napr. 2025-03-01)
            $table->date('valid_from')->index();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_definitions');
    }
};
