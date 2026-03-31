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

        \App\Models\User::chunkById(100, function ($users) {
            foreach ($users as $user) {
                // 1. LIKVIDNÁ HOTOVOSŤ (Banky + Šuflík + Rezerva)
                $liquidAccounts = \App\Models\Account::where('user_id', $user->id)
                    ->whereIn('type', ['bank', 'cash', 'reserve'])
                    ->get();
                    
                $liquidCashBD = BigDecimal::zero();
                foreach ($liquidAccounts as $acc) {
                    $liquidCashBD = $liquidCashBD->plus(\App\Services\CurrencyService::convertToEur((string)$acc->balance, $acc->currency_id));
                }

                // 2. INVESTIČNÝ MAJETOK
                $investments = \App\Models\Investment::where('user_id', $user->id)
                    ->where('is_archived', false)
                    ->get();
                
                // Trhová hodnota cenných papierov
                $securitiesValueBD = BigDecimal::zero();
                foreach ($investments as $inv) {
                    $securitiesValueBD = $securitiesValueBD->plus((string)($inv->current_market_value_eur ?? 0));
                }
                
                // Voľná hotovosť na investičných účtoch (u brokera)
                $brokerAccounts = \App\Models\Account::where('user_id', $user->id)
                    ->where('type', 'investment')
                    ->get();
                    
                $brokerCashBD = BigDecimal::zero();
                foreach ($brokerAccounts as $acc) {
                    $brokerCashBD = $brokerCashBD->plus(\App\Services\CurrencyService::convertToEur((string)$acc->balance, $acc->currency_id));
                }

                $investedTotalBD = BigDecimal::zero();
                foreach ($investments as $inv) {
                    $investedTotalBD = $investedTotalBD->plus((string)($inv->total_invested_eur ?? 0));
                }

                $marketValueTotalBD = $securitiesValueBD->plus($brokerCashBD);

                // 3. ULOŽENIE SNÍMKY
                \App\Models\PortfolioSnapshot::updateOrCreate(
                    ['user_id' => $user->id, 'recorded_at' => now()->toDateString()],
                    [
                        'total_invested_eur' => $investedTotalBD->toScale(4, RoundingMode::HALF_UP)->__toString(),
                        'total_liquid_cash_eur' => $liquidCashBD->toScale(4, RoundingMode::HALF_UP)->__toString(),
                        'total_market_value_eur' => $marketValueTotalBD->toScale(4, RoundingMode::HALF_UP)->__toString(),
                    ]
                );
            }
        });

        $this->info('Snímky uložené.');
    }
}
