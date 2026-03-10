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
        Schema::table('investments', function (Blueprint $table) {
            $table->string('sector')->nullable()->after('broker');
            $table->string('industry')->nullable()->after('sector');
            $table->string('country')->nullable()->after('industry');
            $table->string('asset_type')->nullable()->after('country')->comment('e.g., Equity, ETF, Crypto, Bond');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('investments', function (Blueprint $table) {
            $table->dropColumn(['sector', 'industry', 'country', 'asset_type']);
        });
    }
};
