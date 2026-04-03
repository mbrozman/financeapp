<?php

use App\Http\Controllers\MigrationController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/migrate', [MigrationController::class, 'forceSchemaFix']);
Route::get('/schema-fix', [MigrationController::class, 'forceSchemaFix']);
Route::get('/fresh', [MigrationController::class, 'fresh']);
Route::get('/refresh-all', [MigrationController::class, 'refreshAll']);
Route::get('/debug-investments', [MigrationController::class, 'debugInvestments']);

// Záchranná trasa pre synchronizáciu dát na serveri (Google Cloud)
Route::get('/admin/sync-portfolio-data', function () {
    // Zabezpečenie: Vyžadujeme APP_KEY v URL pre bezpečnosť
    if (request('key') !== config('app.key')) {
        return response('Unauthorized. Please provide the correct key.', 403);
    }

    echo "1. AKTUALIZUJEM TRHOVÉ CENY AKCIÍ...<br>";
    \Illuminate\Support\Facades\Artisan::call('app:update-stock-prices');
    echo \Illuminate\Support\Facades\Artisan::output() . "<br>";

    echo "2. Vytváram snímku portfólia pre všetkých používateľov...<br>";
    \Illuminate\Support\Facades\Artisan::call('app:take-portfolio-snapshot');
    echo \Illuminate\Support\Facades\Artisan::output() . "<br>";

    echo "3. Generujem históriu pre všetkých používateľov...<br>";
    \App\Models\User::all()->each(function ($user) {
        $latest = \App\Models\PortfolioSnapshot::where('user_id', $user->id)->orderBy('recorded_at', 'desc')->first();
        if ($latest) {
            // Body potrebné pre tabuľku: 1D, 1W, 1M, 3M, 6M, 1Y
            $historyDays = [1, 7, 31, 91, 183, 366]; 
            
            // Pridáme aj špecifický štart roka (YTD)
            $ytdDate = now()->startOfYear()->subDay(); 
            
            $dates = collect($historyDays)->map(fn($days) => now()->subDays($days)->toDateString());
            $dates->push($ytdDate->toDateString());

            foreach ($dates as $dateString) {
                \App\Models\PortfolioSnapshot::updateOrCreate(
                    ['user_id' => $user->id, 'recorded_at' => $dateString],
                    [
                        'total_invested_eur' => $latest->total_invested_eur,
                        'total_liquid_cash_eur' => $latest->total_liquid_cash_eur,
                        'total_market_value_eur' => $latest->total_market_value_eur
                    ]
                );
            }
            echo " - História synchronizovaná pre: {$user->email}<br>";
        }
    });

    return "<br><b>DÁTA BOLI ÚSPEŠNE SYNCHRONIZOVANÉ.</b> Osviežte Dashboard.";
});

// Spúšťač pre Google Cloud Scheduler (Cron)
Route::match(['get', 'post'], '/cloud-run/scheduler/{token}', function ($token) {
    // Overenie prístupu cez tajný kľúč
    if ($token !== config('app.cron_token')) {
        return response()->json(['error' => 'Laravel: Invalid Cron Token'], 403);
    }

    // Spustenie Laravel Scheduleru
    \Illuminate\Support\Facades\Artisan::call('schedule:run');
    
    return response()->json([
        'status' => 'success',
        'output' => Artisan::output(),
        'execution_time' => now()->toDateTimeString(),
    ]);
});

// DOČASNÁ TRASA PRE MIGRÁCIU - Po úspešnom spustení na serveri ju prosím zmaž!
Route::get('/spusti-migraciu-12345', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        return "✅ Databáza úspešne zmigrovaná!<br><br><b>Výstup:</b><br><pre>" . Artisan::output() . "</pre>";
    } catch (\Exception $e) {
        return "❌ Chyba pri migrácii: " . $e->getMessage();
    }
});

// DIAGNOSTIKA DATABÁZY - Kontrola existujúcich stĺpcov
Route::get('/diagnostika-db', function () {
    $table = 'financial_plan_items';
    $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
    $hasGoalId = \Illuminate\Support\Facades\Schema::hasColumn($table, 'goal_id');

    return response()->json([
        'tabulka' => $table,
        'zoznam_stlpcov' => $columns,
        'ma_goal_id' => $hasGoalId,
        'migration_status' => Artisan::call('migrate:status'),
        'migration_output' => Artisan::output(),
    ]);
});
