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
        Schema::create('investment_plans', function (Blueprint $table) {
            $table->id();
            if (config('database.default') === 'sqlite') {
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete(); // Broker account to subtract cash from
            $table->string('amount'); // BigDecimal string
            $table->foreignId('currency_id')->constrained();
            $table->string('frequency'); // daily, weekly, monthly
            $table->date('next_run_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_plans');
    }
};
