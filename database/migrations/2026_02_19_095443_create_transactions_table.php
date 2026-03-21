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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            // Multi-tenancy
            if (config('database.default') === 'sqlite') {
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }

            // Na ktorom účte sa pohyb stal?
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();

            // Do akej kategórie patrí? (nullable, lebo prevod medzi účtami nemusí mať kategóriu)
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            // Suma transakcie. Používame 19,4 pre maximálnu presnosť.
            $table->decimal('amount', 19, 4);

            // Dátum transakcie (indexovaný pre rýchle hľadanie a grafy)
            $table->date('transaction_date')->index();

            $table->string('description')->nullable(); // Poznámka k platbe

            // Typ pohybu: income (príjem), expense (výdavok), transfer (prevod)
            $table->string('type');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
