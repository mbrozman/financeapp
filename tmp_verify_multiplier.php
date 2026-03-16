<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CurrencyService;
use App\Models\Currency;

echo "--- FINAL MULTIPLIER VERIFICATION ---\n";

$usd = Currency::where('code', 'USD')->first();
echo "Current USD Rate (Multiplier): {$usd->exchange_rate}\n";

$amountUsd = 89;
$resultEur = CurrencyService::convertToEur($amountUsd, $usd->id);

echo "Calculation: {$amountUsd} USD * {$usd->exchange_rate} = {$resultEur} EUR\n";

// Expecting approx 89 * 0.8714 = 77.55
if (abs((float)$resultEur - 77.55) < 1.0) {
    echo "SUCCESS: Calculation aligns with Multiplier logic.\n";
} else {
    echo "FAILURE: Calculation does not match expected multiplier result.\n";
}

echo "--- Test Finished ---\n";
