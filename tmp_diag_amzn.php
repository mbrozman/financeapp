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
echo "Current Price: " . $investment->current_price . "\n";

foreach ($investment->transactions as $t) {
    echo sprintf(
        "ID: %d | Type: %s | Qty: %s | Price: %s | Comm: %s | Rate: %s | Date: %s\n",
        $t->id,
        $t->type->value,
        $t->quantity,
        $t->price_per_unit,
        $t->commission ?? 0,
        $t->exchange_rate,
        $t->transaction_date
    );
}
