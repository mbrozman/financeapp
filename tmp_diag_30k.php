<?php

use App\Models\Investment;
use App\Models\Account;
use App\Models\InvestmentTransaction;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = '019d07df-2ae4-7080-98af-0b4e8f061599';

echo "--- INVESTMENTS ---\n";
$investments = Investment::where('user_id', $userId)->get();
foreach ($investments as $inv) {
    echo "ID: {$inv->id}, Ticker: {$inv->ticker}, Value (EUR): {$inv->current_market_value_eur}, Invested (EUR): {$inv->total_invested_eur}\n";
}

echo "\n--- BROKER ACCOUNTS ---\n";
$accounts = Account::where('user_id', $userId)->where('type', 'investment')->get();
foreach ($accounts as $acc) {
    echo "ID: {$acc->id}, Name: {$acc->name}, Balance: {$acc->balance}, Currency: {$acc->currency->code}\n";
}

echo "\n--- CALCULATION SIMULATION (InvestmentKpiOverview) ---\n";
$totalValue = 0;
foreach($investments as $inv) {
    $totalValue += (float)$inv->current_market_value_eur;
}

$totalBroker = 0;
foreach ($accounts as $acc) {
    $balanceEur = \App\Services\CurrencyService::convertToEur((string)$acc->balance, $acc->currency_id);
    $totalBroker += (float)$balanceEur;
    echo "Broker {$acc->name} contributing: {$balanceEur} EUR\n";
}

echo "Total Assets: {$totalValue}\n";
echo "Total Broker Cash: {$totalBroker}\n";
echo "GRAND TOTAL (Overview): " . ($totalValue + $totalBroker) . "\n";
