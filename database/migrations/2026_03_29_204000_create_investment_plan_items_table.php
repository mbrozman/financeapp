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
        Schema::create('investment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investment_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('investment_id')->constrained()->cascadeOnDelete();
            $table->decimal('weight', 5, 2)->default(100.00); // Percentuálna váha (napr. 80.00 %)
            $table->timestamps();
        });

        // Migrácia existujúcich dát
        $plans = DB::table('investment_plans')->whereNotNull('investment_id')->get();
        foreach ($plans as $plan) {
            DB::table('investment_plan_items')->insert([
                'investment_plan_id' => $plan->id,
                'investment_id' => $plan->investment_id,
                'weight' => 100.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('investment_plan_items');
    }
};
