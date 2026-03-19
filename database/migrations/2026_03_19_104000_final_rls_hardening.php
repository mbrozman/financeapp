<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = [
            'transactions',
            'users',
            'password_reset_tokens'
        ];

        $hasAuth = DB::select("SELECT 1 FROM information_schema.routines WHERE routine_schema = 'auth' AND routine_name = 'uid'");

        foreach ($tables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Zapnutie RLS
            DB::statement("ALTER TABLE \"{$tableName}\" ENABLE ROW LEVEL SECURITY");
            
            // Vyčistenie starých politík
            DB::statement("DROP POLICY IF EXISTS \"Users can only access their own records\" ON \"{$tableName}\"");

            // Politika pre používateľské dáta (iba ak sme v prostredí Supabase)
            if (!empty($hasAuth)) {
                if (Schema::hasColumn($tableName, 'user_id')) {
                    DB::statement("CREATE POLICY \"Users can only access their own records\" ON \"{$tableName}\" FOR ALL USING (auth.uid() = user_id)");
                } elseif ($tableName === 'users') {
                     // Pre tabuľku users: Používateľ vidí iba seba
                     DB::statement("CREATE POLICY \"Users can only access their own records\" ON \"{$tableName}\" FOR ALL USING (auth.uid() = id)");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $tables = ['transactions', 'users', 'password_reset_tokens'];
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                DB::statement("ALTER TABLE \"{$tableName}\" DISABLE ROW LEVEL SECURITY");
            }
        }
    }
};
