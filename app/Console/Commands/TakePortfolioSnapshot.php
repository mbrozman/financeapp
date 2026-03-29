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
            // 1. LIKVIDNÁ HOTOVOSŤ (Banky + Šuflík + Rezerva)
            $liquidCash = \App\Models\Account::where('user_id', $user->id)
                ->whereIn('type', ['bank', 'cash', 'reserve'])
                ->get()
                ->sum(fn($acc) => (float)\App\Services\CurrencyService::convertToEur((string)$acc->balance, $acc->currency_id));

            // 2. INVESTIČNÝ MAJETOK
            $investments = \App\Models\Investment::where('user_id', $user->id)
                ->where('is_archived', false)
                ->get();
            
            // Trhová hodnota cenných papierov
            $securitiesValue = $investments->sum('current_market_value_eur');
            
            // Voľná hotovosť na investičných účtoch (u brokera)
            $brokerCash = \App\Models\Account::where('user_id', $user->id)
                ->where('type', 'investment')
                ->get()
                ->sum(fn($acc) => (float)\App\Services\CurrencyService::convertToEur((string)$acc->balance, $acc->currency_id));

            $investedTotal = $investments->sum('total_invested_eur');

            // 3. ULOŽENIE SNÍMKY
            \App\Models\PortfolioSnapshot::updateOrCreate(
                ['user_id' => $user->id, 'recorded_at' => now()->toDateString()],
                [
                    'total_invested_eur' => $investedTotal,
                    'total_liquid_cash_eur' => $liquidCash,
                    'total_market_value_eur' => $securitiesValue + $brokerCash, // Investície = Cenné papiere + Hotovosť u brokera
                ]
            );
        });

        $this->info('Snímky uložené.');
    }
}
