<?php

namespace App\Console\Commands;

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Services\StockApiService;
use Illuminate\Console\Command;

class BackfillInvestmentHistory extends Command
{
    protected $signature = 'app:backfill-history {days=365}';
    protected $description = 'Stiahne historické ceny pre všetky investície';

    public function handle(StockApiService $api)
    {
        $days = (int) $this->argument('days');
        $investments = Investment::all();

        foreach ($investments as $investment) {
            $this->info("Sťahujem históriu pre {$investment->ticker}...");
            $api->downloadHistory($investment, $days);
        }

        $this->info('Hotovo!');
    }
}