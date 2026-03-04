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
        // Tabuľka Accounts (naši Brokeri)
        Schema::table('accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('accounts', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            $table->softDeletes(); // Pridá stĺpec deleted_at
        });

        // Tabuľka Investment Categories
        Schema::table('investment_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('investment_categories', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            $table->softDeletes();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('brokers_and_categories', function (Blueprint $table) {
            //
        });
    }
};
