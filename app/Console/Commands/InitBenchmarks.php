<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Investment;
use App\Models\InvestmentCategory;
use App\Models\Account;
use App\Models\Currency;
use App\Services\StockApiService;
use App\Enums\AssetType;

class InitBenchmarks extends Command
{
    protected $signature = 'app:init-benchmarks';
    protected $description = 'Initializes SPY and QQQ as system benchmarks and downloads history';

    public function handle()
    {
        $user = \App\Models\User::first();
        if (!$user) {
            $this->error("No user found.");
            return;
        }
        $userId = $user->id;

        $usd = Currency::where('code', 'USD')->first();
        if (!$usd) {
            $this->error("USD currency not found.");
            return;
        }
        $usdId = $usd->id;
        
        $this->info("Initializing benchmarks for user: {$userId}...");

        // 1. Ensure Category exists
        $category = InvestmentCategory::updateOrCreate(
            ['user_id' => $userId, 'name' => 'Indexové Fondy (Benchmark)'],
            [
                'slug' => 'benchmark',
                'color' => '#64748b',
                'is_active' => true,
            ]
        );

        // 2. Ensure System Account exists
        $account = Account::updateOrCreate(
            ['user_id' => $userId, 'name' => 'System Benchmark'],
            [
                'type' => 'investment',
                'currency_id' => $usdId,
                'balance' => 0,
                'is_active' => true,
            ]
        );

        $benchmarks = [
            'SPY' => 'S&P 500 Index',
            'QQQ' => 'Nasdaq 100 Index',
        ];

        $api = app(StockApiService::class);

        foreach ($benchmarks as $ticker => $name) {
            $this->info("Setting up {$ticker}...");
            
            $investment = Investment::updateOrCreate(
                ['user_id' => $userId, 'ticker' => $ticker],
                [
                    'name' => $name,
                    'account_id' => $account->id,
                    'investment_category_id' => $category->id,
                    'currency_id' => $usdId,
                    'broker' => 'System',
                    'current_price' => 0,
                ]
            );

            $this->info("Downloading history for {$ticker} (1825 days)...");
            $success = $api->downloadHistory($investment, 1825);
            
            if ($success) {
                $this->info("History for {$ticker} downloaded successfully.");
            } else {
                $this->error("Failed to download history for {$ticker}.");
            }
        }

        $this->info("Benchmarks initialization complete.");
    }
}
