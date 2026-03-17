<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Investment;
use App\Models\Currency;
use Brick\Math\BigDecimal;
use App\Services\CurrencyService;

$inv = Investment::where('ticker', 'MSFT')->first();
if (!$inv) die("MSFT not found\n");

echo "=== MSFT AUDIT ===\n";
echo "Ticker: " . $inv->ticker . "\n";
echo "Current Price in DB: " . $inv->current_price . " USD\n";
echo "Quantity: " . $inv->totalQuantity . "\n";

foreach ($inv->transactions as $tx) {
    if ($tx->type->value === 'buy') {
        echo "\nTransaction: Buy on " . $tx->transaction_date->format('Y-m-d') . "\n";
        echo "Qty: " . $tx->quantity . "\n";
        echo "Price: " . $tx->price_per_unit . " USD\n";
        echo "Commission in DB: " . $tx->commission . " USD\n";
        echo "Exchange Rate in DB: " . $tx->exchange_rate . "\n";
        
        $volUSD = $tx->quantity * $tx->price_per_unit;
        echo "Volume USD (clean): " . $volUSD . " USD\n";
        
        $volUSDWithComm = $volUSD + $tx->commission;
        echo "Cost USD (with comm): " . $volUSDWithComm . " USD\n";
        
        $costEUR = $volUSDWithComm * $tx->exchange_rate;
        echo "Cost EUR: " . $costEUR . " EUR\n";
    }
}

$currentRate = CurrencyService::getLiveRateById($inv->currency_id);
echo "\nCurrent USD->EUR Rate in DB: " . $currentRate . "\n";

$volUSD = 15 * $inv->current_price;
echo "Current Value USD: " . $volUSD . "\n";
$currentValEUR = $volUSD * $currentRate;
echo "Current Value EUR: " . $currentValEUR . "\n";

echo "Profit USD (no sell comm): " . ($volUSD - 5928.7) . "\n";
echo "Profit EUR: " . ($currentValEUR - 5057.18) . "\n";

