<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;

foreach (['SPY', 'QQQ'] as $ticker) {
    echo "--- Checking $ticker ---\n";
    $inv = Investment::where('ticker', $ticker)->where('is_benchmark', true)->first();
    if (!$inv) {
        echo "Benchmark $ticker not found!\n";
        continue;
    }
    
    $history = InvestmentPriceHistory::where('investment_id', $inv->id)
        ->orderBy('recorded_at', 'desc')
        ->limit(10)
        ->get();
        
    foreach ($history as $h) {
        echo $h->recorded_at . " (ID: " . $h->id . "): " . $h->price . "\n";
    }
    echo "\n";
}
