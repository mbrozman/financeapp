<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$investment = \App\Models\Investment::where('ticker', 'AMZN')->first();
if (!$investment) {
    echo "Investment not found\n";
    exit;
}

echo "Investment: " . $investment->name . "\n";
echo "Base Currency: " . $investment->currency?->code . " (ID: " . $investment->currency_id . ")\n";
echo "Current Price: " . $investment->current_price . "\n";

echo "--- ALL TRANSACTIONS ---\n";
foreach ($investment->transactions as $tx) {
    echo "ID: {$tx->id} | Type: {$tx->type->value} | Qty: {$tx->quantity} | PriceUSD: {$tx->price_per_unit} | Comm: {$tx->commission} | Rate: {$tx->exchange_rate} | Date: {$tx->transaction_date}\n";
}

echo "--- MODEL CALCULATIONS ---\n";
$stats = \App\Services\InvestmentCalculationService::getStats($investment);
echo "Current Qty: " . $stats['current_quantity'] . "\n";
echo "Avg Buy Price (USD): " . $stats['average_buy_price'] . "\n";
echo "Avg Buy Price (EUR): " . $stats['average_buy_price_eur'] . "\n";
echo "Invested EUR: " . $stats['total_invested_eur'] . "\n";
echo "Gain Base (USD): " . $stats['unrealized_gain_base'] . "\n";
echo "Profit EUR: " . (BigDecimal::of($investment->getCurrentValueForCurrency('EUR'))->minus($stats['total_invested_eur'] ?? 0)) . "\n";
exit();
