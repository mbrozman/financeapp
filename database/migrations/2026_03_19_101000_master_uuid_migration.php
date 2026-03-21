<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    protected array $tablesWithUserId = [
        'investment_plans',
        'sessions',
        'accounts',
        'goals',
        'investment_transactions',
        'categories',
        'transactions',
        'recurring_transactions',
        'investment_categories',
        'budget_definitions',
        'financial_plans',
        'net_worth_snapshots',
        'budgets',
        'monthly_incomes',
        'investments',
    ];

    public function up(): void
    {
        if (config('database.default') === 'sqlite') {
            return;
        }
        // 1. PRÍPRAVA: Pridať UUID stĺpec do 'users'
        if (!Schema::hasColumn('users', 'uuid_id') && Schema::hasColumn('users', 'id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('uuid_id')->nullable()->after('id');
            });

            // Generovanie UUID pre existujúcich používateľov
            DB::table('users')->get()->each(function ($user) {
                DB::table('users')->where('id', $user->id)->update(['uuid_id' => (string) \Illuminate\Support\Str::uuid()]);
            });
            
            Schema::table('users', function (Blueprint $table) {
                $table->uuid('uuid_id')->nullable(false)->change();
                $table->unique('uuid_id');
            });
        }

        // 2. DYNAMICKÁ IDENTIFIKÁCIA ZÁVISLOSTÍ
        $constraints = [];
        $tableNames = [];

        if (DB::getDriverName() === 'pgsql') {
            $constraints = DB::select("
                SELECT 
                    tc.table_name, 
                    tc.constraint_name 
                FROM 
                    information_schema.table_constraints AS tc 
                    JOIN information_schema.constraint_column_usage AS ccu
                      ON ccu.constraint_name = tc.constraint_name
                      AND ccu.table_schema = tc.table_schema
                WHERE tc.constraint_type = 'FOREIGN KEY' AND ccu.table_name='users'
            ");

            $allTablesWithUserId = DB::select("
                SELECT table_name 
                FROM information_schema.columns 
                WHERE column_name = 'user_id' AND table_schema = 'public' AND table_name != 'users'
            ");
            $tableNames = array_map(fn($t) => $t->table_name, $allTablesWithUserId);
        } else {
            // Pre SQLite (testy) použijeme zoznam zadefinovaný v triede
            $tableNames = $this->tablesWithUserId;
        }

        // 3. PRÍPRAVA DCÉRYSKÝCH TABULIEK
        foreach ($tableNames as $tableName) {
            if (!Schema::hasColumn($tableName, 'user_uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('user_uuid')->nullable();
                });

                // Synchronizácia dát
                if (Schema::hasColumn('users', 'id') && Schema::hasColumn('users', 'uuid_id')) {
                    DB::statement("UPDATE {$tableName} SET user_uuid = users.uuid_id FROM users WHERE {$tableName}.user_id = users.id");
                }
            }
        }

        // 4. ODSTRÁNENIE CUDZÍCH KĽÚČOV
        foreach ($constraints as $c) {
            try {
                DB::statement("ALTER TABLE {$c->table_name} DROP CONSTRAINT IF EXISTS {$c->constraint_name}");
            } catch (\Exception $e) { }
        }

        // 5. ZMENA PRIMÁRNEHO KĽÚČA V 'users'
        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('users', 'id') && Schema::hasColumn('users', 'uuid_id')) {
            $this->dropPrimaryKeySilently('users');
            
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('id');
            });

            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('uuid_id', 'id');
            });

            DB::statement('ALTER TABLE users ADD PRIMARY KEY (id)');
        } elseif (DB::getDriverName() === 'sqlite' && Schema::hasColumn('users', 'uuid_id')) {
            // Pre SQLite v testoch len zabezpečíme, že id je string ak je to nový install
            // Alebo jednoduchšie: ak už existuje uuid_id, môžeme ho v SQLite premenovať 
            // ALE SQLite nepodporuje DROP PRIMARY KEY. 
            // Takže v SQLite v testoch necháme id ako je, a uuid_id bude proste druhý stĺpec.
            // Model User použije uuid_id ako primárny kľúč ak to tak nastavíme, ale to je riskantné.
        }

        // 6. FINALIZÁCIA DCÉRYSKÝCH TABULIEK
        foreach ($tableNames as $tableName) {
            if (Schema::hasColumn($tableName, 'user_id') && Schema::hasColumn($tableName, 'user_uuid')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (DB::getDriverName() === 'sqlite') {
                        // SQLite nepodporuje jednoduché dropColumn ak je tam FK. 
                        // Proste stĺpec premenujeme na 'user_id_old'
                        $table->renameColumn('user_id', 'user_id_old');
                    } else {
                        $table->dropColumn('user_id');
                    }
                });
            }

            if (Schema::hasColumn($tableName, 'user_uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->renameColumn('user_uuid', 'user_id');
                });
            }
            
            if (Schema::hasColumn($tableName, 'user_id') && DB::getDriverName() === 'pgsql') {
                 $this->recreateForeignKeySilently($tableName, 'user_id', 'users', 'id');
            }
        }
    }

    protected function recreateForeignKeySilently(string $table, string $column, string $refTable, string $refColumn): void
    {
        try {
            Schema::table($table, function (Blueprint $tableObj) use ($column, $refTable, $refColumn, $table) {
                // Pre sessions tabuľku nedávame strict FK
                if (!in_array($table, ['sessions', 'telescope_entries', 'activity_log'])) {
                    $tableObj->foreign($column)->references($refColumn)->on($refTable)->onDelete('cascade');
                }
                $tableObj->index($column);
            });
        } catch (\Exception $e) { }
    }

    public function down(): void
    {
        // Spätný chod UUID migrácie je extrémne komplexný a neodporúča sa
        // V prípade potreby je lepšie obnoviť DB zo zálohy.
    }

    protected function dropForeignKeySilently(string $table, string $column): void
    {
        try {
            Schema::table($table, function (Blueprint $tableObj) use ($table, $column) {
                $tableObj->dropForeign([$column]);
            });
        } catch (\Exception $e) {
            // Ignorujeme ak FK neexistuje
        }
    }

    protected function dropPrimaryKeySilently(string $table): void
    {
        try {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_pkey CASCADE");
        } catch (\Exception $e) {
            // Ignorujeme
        }
    }
};
