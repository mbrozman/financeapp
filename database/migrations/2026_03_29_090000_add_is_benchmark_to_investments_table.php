<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            // Flag pre benchmark záznamy ktoré nepatria žiadnemu používateľovi
            $table->boolean('is_benchmark')->default(false)->after('is_archived');
        });

        // Urobíme user_id a account_id nullable pre benchmark záznamy
        Schema::table('investments', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn('is_benchmark');
            $table->foreignId('account_id')->nullable(false)->change();
        });
    }
};
