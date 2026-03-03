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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id(); // Automatické ID (1, 2, 3...)
            $table->string('code', 3)->unique(); // Napr. EUR, USD (3 znaky, nesmie sa opakovať)
            $table->string('name'); // Názov: Euro, Americký dolár
            $table->string('symbol', 10); // €, $, btc
            // decimal(celkový počet číslic, počet desatinných miest)
            // 19 celkom, 8 desatinných (dôležité pre krypto kurzy)
            $table->decimal('exchange_rate', 19, 8)->default(1.0);
            $table->timestamps(); // Vytvorí created_at a updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
