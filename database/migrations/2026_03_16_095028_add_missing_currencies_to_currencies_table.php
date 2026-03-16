<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('currencies')->insertOrIgnore([
            ['code' => 'USD', 'name' => 'Americký dolár', 'symbol' => '$', 'exchange_rate' => 1.10, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'CZK', 'name' => 'Česká koruna', 'symbol' => 'Kč', 'exchange_rate' => 25.0, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'GBP', 'name' => 'Britská libra', 'symbol' => '£', 'exchange_rate' => 0.85, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('currencies')->whereIn('code', ['USD', 'CZK', 'GBP'])->delete();
    }
};
