<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InvestmentPlan;
use App\Models\InvestmentTransaction;

$plans = InvestmentPlan::all();

echo "--- INVESTMENT PLANS ---\n";
foreach ($plans as $plan) {
    echo "ID: {$plan->id} | Name: {$plan->name} | Next Run: " . ($plan->next_run_date->toDateString() ?? 'N/A') . " | Active: " . ($plan->is_active ? 'YES' : 'NO') . "\n";
}

echo "\n--- RECENT INVESTMENT TRANSACTIONS ---\n";
$txs = InvestmentTransaction::latest()->take(5)->get();
foreach ($txs as $tx) {
    echo "ID: {$tx->id} | Plan ID: {$tx->investment_plan_id} | Date: {$tx->transaction_date->toDateString()} | Asset: {$tx->investment?->ticker} | Qty: {$tx->quantity}\n";
}
