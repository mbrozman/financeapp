<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MigrationController extends Controller
{
    /**
     * Spustí migrácie na produkcii cez webový endpoint.
     * Vyžaduje 'key' v query stringu zhodný s APP_KEY.
     */
    /**
     * Brute force opravy schémy pre Supabase, ak klasické migrácie zlyhávajú.
     */
    public function forceSchemaFix(Request $request)
    {
        $inputKey = $request->query('key');
        if (empty($inputKey) || $inputKey !== config('app.key')) {
            abort(403);
        }

        try {
            $results = [];

            // 1. Oprava stĺpcov pre INVESTMENTS
            $colsToAdd = [
                'total_quantity' => 'decimal(19,8) DEFAULT 0',
                'average_buy_price' => 'decimal(19,4) DEFAULT 0',
                'average_buy_price_eur' => 'decimal(19,4) DEFAULT 0',
                'total_invested_base' => 'decimal(19,4) DEFAULT 0',
                'total_invested_eur' => 'decimal(19,4) DEFAULT 0',
                'total_sales_base' => 'decimal(19,4) DEFAULT 0',
                'total_sales_eur' => 'decimal(19,4) DEFAULT 0',
                'total_dividends_base' => 'decimal(19,4) DEFAULT 0',
                'realized_gain_base' => 'decimal(19,4) DEFAULT 0',
            ];

            foreach ($colsToAdd as $col => $definition) {
                $exists = \Illuminate\Support\Facades\DB::selectOne("
                    SELECT 1 FROM information_schema.columns 
                    WHERE table_name = 'investments' AND column_name = ?", [$col]);

                if (!$exists) {
                    \Illuminate\Support\Facades\DB::statement("ALTER TABLE investments ADD COLUMN $col $definition");
                    $results[] = "Pridaný stĺpec: $col";
                }
            }

            // 2. Skúsime spustiť standardný migrate (možno sa už "chytí")
            \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            $results[] = "Standardný migrate result: " . \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'status' => 'success',
                'results' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * TOTÁLNY RESET: Vymaže všetko a migruje nanovo.
     * !!! POZOR: TOTO VYMAŽE VŠETKY DÁTA V PRODUKCII !!!
     */
    public function fresh(Request $request)
    {
        $inputKey = $request->query('key');
        if (empty($inputKey) || $inputKey !== config('app.key')) {
            abort(403);
        }

        try {
            // FORCE BYPASS LOGGING
            config(['logging.default' => 'null']);

            \Illuminate\Support\Facades\Artisan::call('migrate:fresh', [
                '--force' => true,
                '--seed' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Databáza bola kompletne vymazaná a nanovo migrovaná (Fresh Start).',
                'output' => \Illuminate\Support\Facades\Artisan::output(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chyba pri resete databázy.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
