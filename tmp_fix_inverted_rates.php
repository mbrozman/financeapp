<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Currency;
use App\Models\InvestmentTransaction;
use Illuminate\Support\Facades\DB;

echo "--- STARTING DATA INVERSION FIX ---\n";

DB::transaction(function () {
    // 1. Fix Currencies (where rate > 1, it's likely old ECB format)
    // Actually, we'll invert ALL non-EUR rates that look like ECB rates.
    // If USD is 1.14 -> 1 / 1.14 = 0.87 (correct multiplier)
    // If USD is already 0.91 -> 1 / 0.91 = 1.09 (we invert it back if it was already multiplier? No, let's be careful).
    // The user said $89 -> €95. 95/89 = 1.067. 
    // If they have 1.067 in DB, and we use divider, 89 / 1.067 = 83 (correct).
    // Wait, if they have 1.14 in DB (USD for 1 EUR):
    // Old: 89 / 1.14 = 78 EUR.
    // New (Multiplier): 89 * 1.14 = 101 EUR.
    // If they have 0.87 (EUR for 1 USD):
    // Old: 89 / 0.87 = 102 EUR.
    // New (Multiplier): 89 * 0.87 = 77 EUR.
    
    // User said: $89 -> €95.
    // 89 * X = 95 -> X = 1.06
    // 89 / X = 95 -> X = 0.93
    // So if they have 0.93 in DB and we did division, they got 95.
    // If we switch to Multiplication, they need to keep 0.93.
    
    // WAIT. If they have 1.14 in DB (ECB) and we did division, they got 78.
    // If we switch to Multiplication, they need 1/1.14 = 0.87.
    
    // Let's check my previous tmp_check_db_rates.php output:
    // USD: 1.1476
    // CZK: 24.437
    // These are CLEARLY ECB rates ( Foreign / 1 EUR).
    // To use with MULTIPLIER (EUR = USD * Rate), we MUST invert them!
    
    echo "Inverting Currency Rates...\n";
    $currencies = Currency::where('code', '!=', 'EUR')->get();
    foreach ($currencies as $c) {
        if ($c->exchange_rate > 0) {
            $old = $c->exchange_rate;
            $new = 1 / $old;
            $c->update(['exchange_rate' => $new]);
            echo "Currency {$c->code}: {$old} -> {$new}\n";
        }
    }

    echo "Inverting Transaction Rates...\n";
    // Fix existing transactions
    $transactions = InvestmentTransaction::whereHas('currency', fn($q) => $q->where('code', '!=', 'EUR'))->get();
    foreach ($transactions as $t) {
        if ($t->exchange_rate > 0) {
            $old = $t->exchange_rate;
            $new = 1 / $old;
            $t->update(['exchange_rate' => $new]);
            echo "Transaction ID {$t->id} ({$t->investment?->ticker}): {$old} -> {$new}\n";
        }
    }
});

echo "--- DATA INVERSION FINISHED ---\n";
