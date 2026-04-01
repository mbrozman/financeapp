<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\DashboardFinanceService;
use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Support\Facades\Cache;

$account = Account::first();
if (!$account) {
    die("❌ Error: No accounts found in DB.\n");
}

$userId = $account->user_id;
$year = 2026;
$key = DashboardFinanceService::getYearlyCashflowCacheKey($userId, $year);

echo "Checking cache for key: $key (User: $userId, Acc: {$account->id})\n";

// 1. Zabezpečíme, že cache existuje
app(DashboardFinanceService::class)->getYearlyCashflow($userId, $year);

if (Cache::has($key)) {
    echo "✅ Cache success: Key exists.\n";
} else {
    echo "❌ Cache failure: Key does not exist.\n";
}

// 2. Vytvoríme transakciu
echo "Creating test transaction...\n";
$tx = Transaction::create([
    'user_id' => $userId,
    'account_id' => $account->id,
    'type' => 'expense',
    'amount' => 10,
    'transaction_date' => '2026-04-01',
    'description' => 'Test Cache Invalidation',
]);

// 3. Overíme, či bola cache vymazaná
if (!Cache::has($key)) {
    echo "✅ Invalidation success: Key was cleared after transaction creation.\n";
} else {
    echo "❌ Invalidation failure: Key still exists!\n";
}

// 4. Vymažeme testovaciu transakciu
$tx->delete();

if (!Cache::has($key)) {
    echo "✅ Invalidation success: Key was cleared after transaction deletion.\n";
} else {
    echo "❌ Invalidation failure: Key still exists after deletion!\n";
}
