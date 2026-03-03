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
       Schema::table('transactions', function (Blueprint $table) {
        // Pridáme stĺpec, ktorý môže byť prázdny (nullable)
        $table->string('attachment')->nullable()->after('description');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
         Schema::table('transactions', function (Blueprint $table) {
        $table->dropColumn('attachment');
    });
    }
};
