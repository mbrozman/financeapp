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
        Schema::create('investment_dividends', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            
            // Relácia k investícii (s kaskádovým mazaním - ak zmažem investíciu, zmažú sa dividendy)
            $table->foreignId('investment_id')
                  ->constrained('investments')
                  ->cascadeOnDelete();
                  
            // Suma čistej (Net) dividendy - ukladáme so 4 desatinnými číslami pre presnosť
            $table->decimal('amount', 18, 4);
            
            // Manažment mien - v čom nám dividenda reálne prišla?
            $table->foreignId('currency_id')->constrained('currencies');
            // Kurz, aký bol v čase pripísania (pre historickú presnosť)
            $table->decimal('exchange_rate', 18, 4)->default(1.0000);
            
            // Kedy nám reálne prišli peniaze na účet
            $table->date('payout_date');
            
            // Tlačidlo "Pripočítaj k voľnej hotovosti na brokerskom účte"
            $table->boolean('add_to_broker_balance')->default(true);
            
            $table->text('notes')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_dividends');
    }
};
