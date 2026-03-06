<?php

namespace App\Console\Commands;

use App\Models\PortfolioSnapshot; // Uisti sa, že názov modelu sedí
use Illuminate\Console\Command;
use Carbon\Carbon;

class BackfillNetWorth extends Command
{
    protected $signature = 'app:backfill-net-worth {start_amount} {months=6}';
    protected $description = 'Vytvorí historické snapshoty majetku pre testovanie grafu';

    public function handle()
    {
        $amount = (float) $this->argument('start_amount');
        $months = (int) $this->argument('months');
        $user_id = 1; // Tvoje ID

        $this->info("Generujem snapshoty za posledných {$months} mesiacov...");

        for ($i = ($months * 30); $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            // Simulujeme mierny rast reality (náhodne pridáme/uberieme pár eur)
            $amount += rand(-10, 50); 

            \App\Models\PortfolioSnapshot::updateOrCreate(
                ['user_id' => $user_id, 'recorded_at' => $date->toDateString()],
                [
                    'total_invested_eur' => $amount * 0.7,
                    'total_liquid_cash_eur' => $amount * 0.3,
                    'total_market_value_eur' => $amount,
                ]
            );
        }

        $this->info('Hotovo! Snapshoty boli vytvorené.');
    }
}