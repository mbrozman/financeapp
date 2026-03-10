<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$ticker = 'AAPL';
$response = Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
])->get("https://query2.finance.yahoo.com/v10/finance/quoteSummary/{$ticker}?modules=assetProfile,quoteType");

if ($response->successful()) {
    $data = $response->json();
    $result = $data['quoteSummary']['result'][0] ?? null;
    print_r($result);
} else {
    echo "Failed to fetch data. Status: " . $response->status() . "\n";
}
