<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Investment;
use App\Models\PortfolioSnapshot;
use Illuminate\Console\Command;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class TakePortfolioSnapshot extends Command
{
    protected $signature = 'app:take-portfolio-snapshot';
    protected $description = 'Uloží dennú snímku hodnoty portfólia pre všetkých užívateľov';

    public function handle()
    {
        $this->info('Snímam čistý majetok užívateľov...');

        \App\Models\User::all()->each(function ($user) {
            // 1. LIKVIDNÁ HOTOVOSŤ (Banky + Šuflík)
            $liquidCash = \App\Models\Account::where('user_id', $user->id)
                ->whereIn('type', ['bank', 'cash'])
                ->get()
                ->sum(fn($acc) => (float)$acc->balance / ($acc->currency?->exchange_rate ?: 1));

            // 2. INVESTIČNÝ MAJETOK
            $investments = \App\Models\Investment::where('user_id', $user->id)
                ->where('is_archived', false)
                ->get();
            $marketValue = $investments->sum('current_market_value_eur');
            $investedTotal = $investments->sum('total_invested_eur');

            // 3. ULOŽENIE SNÍMKY
            \App\Models\PortfolioSnapshot::updateOrCreate( // Ak si premenoval model na NetWorthSnapshot, zmeň názov tu
                ['user_id' => $user->id, 'recorded_at' => now()->toDateString()],
                [
                    'total_invested_eur' => $investedTotal,
                    'total_liquid_cash_eur' => $liquidCash,
                    'total_market_value_eur' => $marketValue + $liquidCash, // Toto je ten "Real Net Worth"
                ]
            );
        });

        $this->info('Snímky uložené.');
    }
}
