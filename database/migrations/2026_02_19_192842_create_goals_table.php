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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            if (config('database.default') === 'sqlite') {
                $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            } else {
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            }

            $table->string('name'); // Názov: "Rezerva", "Nové auto", "Splatenie hypotéky"

            // Cieľová suma, ktorú chceme dosiahnuť
            $table->decimal('target_amount', 19, 4);

            // Aktuálny stav (koľko už máme)
            $table->decimal('current_amount', 19, 4)->default(0);

            // Dokedy to chceme stihnúť (dobrovoľné)
            $table->date('deadline')->nullable();

            // Typ: saving (sporenie) alebo debt (dlh)
            $table->string('type')->default('saving');

            // Farba pre vizualizáciu progresu
            $table->string('color')->default('#3b82f6');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
