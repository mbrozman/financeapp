<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = "HĽADANIE DUPLICÍT SPY\n";
$output .= "=====================\n";

$spys = \App\Models\Investment::withoutGlobalScopes()
    ->where('ticker', 'SPY')
    ->get();

$output .= "Nájdených záznamov: " . $spys->count() . "\n\n";

foreach ($spys as $s) {
    $histCount = \App\Models\InvestmentPriceHistory::where('investment_id', $s->id)->count();
    $output .= "ID: {$s->id} | UserID: {$s->user_id} | Benchmark: " . ($s->is_benchmark ? 'ÁNO' : 'NIE') . " | Počet cien: {$histCount}\n";
}

file_put_contents('diag_results.txt', $output);
echo "Diagnostika duplicit hotova v diag_results.txt\n";
