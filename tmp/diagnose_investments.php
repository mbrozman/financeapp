<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InvestmentPlan;
use App\Models\InvestmentTransaction;
use Carbon\Carbon;

$today = Carbon::now()->toDateString();
$plans = InvestmentPlan::with('items.investment')->where('is_active', true)->get();

echo "Today: $today\n\n";
echo "--- ACTIVE INVESTMENT PLANS ---\n";
foreach ($plans as $plan) {
    echo "ID: {$plan->id} | Name: {$plan->name} | Next Run: " . ($plan->next_run_date ? $plan->next_run_date->toDateString() : 'NULL') . " | Active: " . ($plan->is_active ? 'YES' : 'NO') . "\n";
    foreach ($plan->items as $item) {
        echo "  - Asset: " . ($item->investment ? $item->investment->ticker : 'N/A') . " | Weight: {$item->weight}%\n";
    }
}

echo "\n--- RECENT INVESTMENT TRANSACTIONS (Last 5) ---\n";
$txs = InvestmentTransaction::with('investment')->latest()->take(5)->get();
foreach ($txs as $tx) {
    echo "ID: {$tx->id} | Plan ID: {$tx->investment_plan_id} | Date: " . $tx->transaction_date->toDateString() . " | Asset: " . ($tx->investment ? $tx->investment->ticker : 'N/A') . " | Qty: {$tx->quantity}\n";
}

$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    echo "\n--- LAST 10 LOG LINES ---\n";
    $lines = array_slice(file($logFile), -10);
    echo implode("", $lines);
}
