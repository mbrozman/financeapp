<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Currency;

echo "--- Current Currency Rates in DB ---\n";
$currencies = Currency::all();

foreach ($currencies as $c) {
    echo "ID: {$c->id} | Code: {$c->code} | Symbol: {$c->symbol} | Rate: {$c->exchange_rate}\n";
}
echo "--- End ---\n";
