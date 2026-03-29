<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Services\StockApiService;
use Illuminate\Console\Command;
use App\Models\InvestmentPriceHistory;

class UpdateStockPrices extends Command
{
    protected $signature = 'app:update-stock-prices';
    protected $description = 'Aktualizuje trhové ceny investícií cez API';

    public function handle(StockApiService $api)
    {
        // Bežné investície používateľov
        $investments = Investment::withoutGlobalScopes()
            ->whereNotNull('ticker')
            ->get();

    foreach ($investments as $investment) {
        $this->info("Aktualizujem: {$investment->ticker}...");

        // 1. Získame aktuálnu "živú" cenu
        $liveData = $api->getLiveQuote($investment->ticker);

        if ($liveData) {
            $investment->update([
                'current_price' => $liveData['price'],
                'daily_change_percentage' => $liveData['change_percent'],
                'last_price_update' => now(),
            ]);

            // 2. Zároveň si túto cenu zapíšeme do histórie pre dnešný deň
            InvestmentPriceHistory::updateOrCreate(
                [
                    'investment_id' => $investment->id,
                    'recorded_at' => now()->format('Y-m-d'),
                ],
                [
                    'price' => $liveData['price'],
                ]
            );

            $this->info("Hotovo: {$investment->ticker} = {$liveData['price']}");
        }
        
        // Yahoo je rýchlejší a menej prísny, stačí pauza 1 sekunda
        sleep(3); 
    }
    }
}