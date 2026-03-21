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
        Schema::create('recurring_transactions', function (Blueprint $table) {
            $table->id();
            if (config('database.default') === 'sqlite') {
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->decimal('amount', 19, 4);
            $table->string('type'); // income / expense
            $table->string('interval')->default('monthly'); // weekly, monthly, yearly
            $table->date('next_date');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_transactions');
    }
};
