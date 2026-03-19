<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Zoznam tabuliek, ktoré vyžadujú RLS podľa Supabase linteru.
     */
    protected array $tablesToSecure = [
        // Systémové tabuľky (Bez prístupu cez API)
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'sessions',
        'activity_log',
        
        // Statické/Zdieľané (Read-only pre všetkých)
        'currencies',
        
        // Používateľské dáta (Owner-only)
        'investments',
        'investment_transactions',
        'investment_categories',
        'investment_price_histories',
        'recurring_transactions',
        'goals',
        'financial_plans',
        'financial_plan_items',
        'net_worth_snapshots',
        'budget_definitions',
        'budgets',
        'monthly_incomes',
        'categories',
        'accounts',
        'investment_plans',
    ];

    public function up(): void
    {
        // Iba pre PostgreSQL (Supabase)
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // 3. Definovanie politík (iba ak sme v prostredí Supabase s auth schémou)
        $hasAuth = DB::select("SELECT 1 FROM information_schema.routines WHERE routine_schema = 'auth' AND routine_name = 'uid'");

        foreach ($this->tablesToSecure as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Zapnutie RLS
            DB::statement("ALTER TABLE \"{$tableName}\" ENABLE ROW LEVEL SECURITY");
            
            DB::statement("DROP POLICY IF EXISTS \"Users can only access their own records\" ON \"{$tableName}\"");
            DB::statement("DROP POLICY IF EXISTS \"Public can only read currencies\" ON \"{$tableName}\"");

            if ($tableName === 'currencies') {
                DB::statement("CREATE POLICY \"Public can only read currencies\" ON \"{$tableName}\" FOR SELECT USING (true)");
            } elseif ($this->isUserOwned($tableName) && !empty($hasAuth)) {
                // Dáta vlastnené používateľom (iba ak existuje auth.uid())
                DB::statement("CREATE POLICY \"Users can only access their own records\" ON \"{$tableName}\" FOR ALL USING (auth.uid() = user_id)");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tablesToSecure as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            DB::statement("ALTER TABLE \"{$tableName}\" DISABLE ROW LEVEL SECURITY");
        }
    }

    protected function isUserOwned(string $tableName): bool
    {
        return Schema::hasColumn($tableName, 'user_id');
    }
};
