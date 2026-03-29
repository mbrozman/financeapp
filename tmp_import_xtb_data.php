<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Account;
use App\Models\Transaction;
use App\Enums\TransactionType;
use App\Services\InvestmentCalculationService;
use Illuminate\Support\Str;

$userId = '019d07df-2ae4-7080-98af-0b4e8f061599';
$user = User::findOrFail($userId);
$xtbAccountId = 11; // XTB Broker EUR
$bankAccountId = 8; // Prima banka (Example source)

echo "Starting import for User: " . $user->email . "\n";

// --- 1. DEFINÍCIA KOORDINÁTOROV ---
$eurId = \App\Models\Currency::where('code', 'EUR')->first()?->id ?? 4;
$usdId = \App\Models\Currency::where('code', 'USD')->first()?->id ?? 1;

$akcieCatId = \App\Models\InvestmentCategory::where('user_id', $userId)->where('name', 'LIKE', 'Akcie%')->first()?->id ?? 3;
$etfCatId = \App\Models\InvestmentCategory::where('user_id', $userId)->where('name', 'LIKE', 'ETF%')->first()?->id ?? 4;

$investmentsData = [
    'VWCE.DE' => ['name' => 'Vanguard FTSE All-World UCITS ETF', 'type' => 'ETF', 'cat_id' => $etfCatId, 'currency_id' => $eurId],
    'GOOGC.US' => ['name' => 'Alphabet Inc Class C', 'type' => 'Equity', 'cat_id' => $akcieCatId, 'currency_id' => $usdId],
    'INTC.US' => ['name' => 'Intel Corporation', 'type' => 'Equity', 'cat_id' => $akcieCatId, 'currency_id' => $usdId],
    'NOVOB.DK' => ['name' => 'Novo Nordisk A/S', 'type' => 'Equity', 'cat_id' => $akcieCatId, 'currency_id' => $eurId],
    'WBD.US' => ['name' => 'Warner Bros. Discovery, Inc.', 'type' => 'Equity', 'cat_id' => $akcieCatId, 'currency_id' => $usdId],
    'PFE.US' => ['name' => 'Pfizer Inc.', 'type' => 'Equity', 'cat_id' => $akcieCatId, 'currency_id' => $usdId],
];

$investmentModels = [];
foreach ($investmentsData as $ticker => $d) {
    $investmentModels[$ticker] = Investment::firstOrCreate(
        ['user_id' => $userId, 'ticker' => $ticker],
        [
            'account_id' => $xtbAccountId,
            'name' => $d['name'],
            'asset_type' => $d['type'],
            'investment_category_id' => $d['cat_id'],
            'currency_id' => $d['currency_id'],
            'broker' => 'XTB',
            'is_archived' => false,
        ]
    );
}

// --- 2. VKLADY (Deposits to Broker) ---
$deposits = [
    ['date' => '2025-08-15', 'amount' => 400],
    ['date' => '2025-09-17', 'amount' => 400],
    ['date' => '2025-10-15', 'amount' => 400],
];

foreach ($deposits as $d) {
    // Record as Internal Transfer (Prima -> XTB)
    Transaction::create([
        'user_id' => $userId,
        'account_id' => $bankAccountId,
        'amount' => -$d['amount'],
        'type' => 'expense',
        'category_id' => null,
        'transaction_date' => $d['date'],
        'note' => 'Vklad na XTB'
    ]);
    Transaction::create([
        'user_id' => $userId,
        'account_id' => $xtbAccountId,
        'amount' => $d['amount'],
        'type' => 'income',
        'category_id' => null,
        'transaction_date' => $d['date'],
        'note' => 'Vklad z banky'
    ]);
}
echo "Deposits imported.\n";

// --- 3. TRANSACKIE (Buys, Sells, Dividends) ---

// VWCE Weekly Buys (March 2026)
$vwceBuys = [
    ['date' => '2026-03-09', 'qty' => 0.2052, 'price' => 120.61, 'comm' => 0],
    ['date' => '2026-03-16', 'qty' => 0.2045, 'price' => 121.03, 'comm' => 0],
    ['date' => '2026-03-23', 'qty' => 0.2038, 'price' => 121.44, 'comm' => 0],
];
foreach($vwceBuys as $b) {
    InvestmentTransaction::create([
        'investment_id' => $investmentModels['VWCE.DE']->id,
        'account_id' => $xtbAccountId,
        'type' => TransactionType::BUY,
        'quantity' => $b['qty'],
        'price_per_unit' => $b['price'],
        'commission' => $b['comm'],
        'currency_id' => $eurId,
        'transaction_date' => $b['date'],
    ]);
}

// GOOGC Dividend
InvestmentTransaction::create([
    'investment_id' => $investmentModels['GOOGC.US']->id,
    'account_id' => $xtbAccountId,
    'type' => TransactionType::DIVIDEND,
    'quantity' => 20, 
    'price_per_unit' => 0.106, 
    'commission' => 0.90, 
    'currency_id' => $eurId, 
    'transaction_date' => '2025-09-15',
]);

// CLOSED TRADE: INTC.US 
$intc = $investmentModels['INTC.US'];
InvestmentTransaction::create([
    'investment_id' => $intc->id,
    'account_id' => $xtbAccountId,
    'type' => TransactionType::BUY,
    'quantity' => 50,
    'price_per_unit' => 34.50,
    'currency_id' => $usdId,
    'transaction_date' => '2024-07-14',
]);
InvestmentTransaction::create([
    'investment_id' => $intc->id,
    'account_id' => $xtbAccountId,
    'type' => TransactionType::SELL,
    'quantity' => 50,
    'price_per_unit' => 21.20,
    'currency_id' => $usdId,
    'transaction_date' => '2025-08-01',
]);

// CLOSED TRADE: NOVOB.DK
$novo = $investmentModels['NOVOB.DK'];
InvestmentTransaction::create(['investment_id' => $novo->id, 'account_id' => $xtbAccountId, 'type' => TransactionType::BUY, 'quantity' => 12, 'price_per_unit' => 105.40, 'currency_id' => $eurId, 'transaction_date' => '2025-01-23']);
InvestmentTransaction::create(['investment_id' => $novo->id, 'account_id' => $xtbAccountId, 'type' => TransactionType::SELL, 'quantity' => 12, 'price_per_unit' => 105.05, 'currency_id' => $eurId, 'transaction_date' => '2025-02-13']);

echo "Transactions imported.\n";

// --- 4. REFRESH STATS ---
foreach ($investmentModels as $im) {
    InvestmentCalculationService::refreshStats($im);
}
echo "Stats refreshed.\n";

echo "Import finished successfully.\n";
