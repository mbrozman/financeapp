<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_log')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            // Meníme bigint na string (36), aby sme podporovali UUID aj staré Integer ID
            $table->string('causer_id', 36)->nullable()->change();
            $table->string('subject_id', 36)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_log')) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table) {
            // Späť na bigint (Pozor: môže zlyhať ak sú v DB už reálne UUIDs)
            $table->bigInteger('causer_id')->nullable()->change();
            $table->bigInteger('subject_id')->nullable()->change();
        });
    }
};
