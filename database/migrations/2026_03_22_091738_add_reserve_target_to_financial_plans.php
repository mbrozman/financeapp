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
        Schema::table('financial_plans', function (Blueprint $table) {
            $table->decimal('reserve_target', 15, 2)->default(0)->after('expected_annual_return');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_plans', function (Blueprint $table) {
            //
        });
    }
};
