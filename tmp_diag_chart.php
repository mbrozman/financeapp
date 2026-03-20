<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;

$inv = Investment::where('ticker', 'AMZN')->first();
if (!$inv) {
    die("AMZN not found\n");
}

echo "Investment ID: " . $inv->id . "\n";
echo "ID Type: " . gettype($inv->id) . "\n";

$historyCount = InvestmentPriceHistory::where('investment_id', $inv->id)->count();
echo "History count: " . $historyCount . "\n";

$firstHistory = InvestmentPriceHistory::where('investment_id', $inv->id)->orderBy('recorded_at', 'asc')->first();
if ($firstHistory) {
    echo "First history record investment_id: " . $firstHistory->investment_id . "\n";
    echo "First history record investment_id Type: " . gettype($firstHistory->investment_id) . "\n";
} else {
    echo "No history found for this ID.\n";
    // Let's check if any history exists at all
    $totalHistory = InvestmentPriceHistory::count();
    echo "Total history records in DB: " . $totalHistory . "\n";
    if ($totalHistory > 0) {
        $sample = InvestmentPriceHistory::first();
        echo "Sample history record investment_id: " . $sample->investment_id . "\n";
    }
}
