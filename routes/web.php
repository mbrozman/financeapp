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

    echo "1. Inicializujem benchmarky...<br>";
    \Illuminate\Support\Facades\Artisan::call('app:init-benchmarks');
    echo \Illuminate\Support\Facades\Artisan::output() . "<br>";

    echo "2. AKTUALIZUJEM CENY VŠETKÝCH AKCIÍ...<br>";
    \Illuminate\Support\Facades\Artisan::call('app:update-stock-prices');
    echo \Illuminate\Support\Facades\Artisan::output() . "<br>";

    // Spätná oprava is_benchmark príznaku
    \App\Models\Investment::whereIn('ticker', ['SPY', 'QQQ'])->update(['is_benchmark' => true]);

    echo "3. Vytváram snímku portfólia pre všetkých používateľov...<br>";
    \Illuminate\Support\Facades\Artisan::call('app:take-portfolio-snapshot');
    echo \Illuminate\Support\Facades\Artisan::output() . "<br>";

    echo "4. Generujem históriu (7 dní) pre všetkých používateľov...<br>";
    \App\Models\User::all()->each(function ($user) {
        $latest = \App\Models\PortfolioSnapshot::where('user_id', $user->id)->orderBy('recorded_at', 'desc')->first();
        if ($latest) {
            for ($i = 1; $i <= 7; $i++) {
                \App\Models\PortfolioSnapshot::updateOrCreate(
                    ['user_id' => $user->id, 'recorded_at' => now()->subDays($i)->toDateString()],
                    [
                        'total_invested_eur' => $latest->total_invested_eur,
                        'total_liquid_cash_eur' => $latest->total_liquid_cash_eur,
                        'total_market_value_eur' => $latest->total_market_value_eur
                    ]
                );
            }
            echo " - História vygenerovaná pre užívateľa: {$user->email}<br>";
        }
    });

    return "<br><b>DÁTA BOLI ÚSPEŠNE SYNCHRONIZOVANÉ.</b> Osviežte Dashboard.";
});
