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
        // V PostgreSQL nemusia stačiť standardné Laravel zmeny pre foreignId, ak existuje constraint.
        // Najprv zistíme názov constraintu a odstránime ho, ak existuje.
        
        $table = 'investment_plans';
        $column = 'investment_id';

        // PostgreSQL syntax pre zmenu na NULLABLE
        DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} DROP NOT NULL;");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE investment_plans ALTER COLUMN investment_id SET NOT NULL;");
    }
};
