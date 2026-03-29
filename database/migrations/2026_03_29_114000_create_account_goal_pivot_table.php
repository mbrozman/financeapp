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
        Schema::create('account_goal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        // Migrate existing data from goals.account_id to account_goal pivot table if column exists
        if (Schema::hasColumn('goals', 'account_id')) {
            $goals = DB::table('goals')->whereNotNull('account_id')->get();
            foreach ($goals as $goal) {
                DB::table('account_goal')->insert([
                    'account_id' => $goal->account_id,
                    'goal_id' => $goal->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_goal');
    }
};
