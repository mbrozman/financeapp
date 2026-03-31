<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = "1. AKTUALIZUJEM BENCHMARKY (SPY, QQQ)...\n";
\Illuminate\Support\Facades\Artisan::call('app:init-benchmarks');
$output .= \Illuminate\Support\Facades\Artisan::output();

$output .= "\n2. VYTVARAM AKTUALNU SNIMKU PORTFOLIA...\n";
\Illuminate\Support\Facades\Artisan::call('app:take-portfolio-snapshot');
$output .= \Illuminate\Support\Facades\Artisan::output();

$output .= "\n3. GENERUJEM HISTORICKE SNIMKY (7 dni dozadu)...\n";
$user = \App\Models\User::first();
if ($user) {
    $latest = \App\Models\PortfolioSnapshot::where('user_id', $user->id)->orderBy('recorded_at', 'desc')->first();
    if ($latest) {
        for ($i = 1; $i <= 7; $i++) {
            $date = now()->subDays($i)->toDateString();
            \App\Models\PortfolioSnapshot::updateOrCreate(
                ['user_id' => $user->id, 'recorded_at' => $date],
                [
                    'total_invested_eur' => $latest->total_invested_eur,
                    'total_liquid_cash_eur' => $latest->total_liquid_cash_eur,
                    'total_market_value_eur' => $latest->total_market_value_eur
                ]
            );
            $output .= "Snímka pre datum $date vytvorená.\n";
        }
    }
}

$output .= "\n--- PROCES DOKONCENY ---\n";
file_put_contents('run_results.txt', $output);
echo "Vysledky zapisane do run_results.txt\n";
