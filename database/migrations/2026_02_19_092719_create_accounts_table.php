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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            // Cudzí kľúč na užívateľa. 
            // constrained() povie DB, že ak užívateľ neexistuje, nedovolí vytvoriť účet.
            // cascadeOnDelete() povie: ak zmažeš užívateľa, zmaž aj všetky jeho účty.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Cudzí kľúč na menu
            $table->foreignId('currency_id')->constrained();

            $table->string('name'); // Názov: "Môj bežný účet"
            $table->string('type'); // bank, cash, investment, crypto

            // Aktuálny zostatok. 4 desatinné miesta pre presnosť.
            $table->decimal('balance', 19, 4)->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
