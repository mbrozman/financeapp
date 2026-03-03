<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Investment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetWorthOverview extends BaseWidget
{
    protected static ?int $sort = 0; 

    protected function getStats(): array
    {
        // 1. CELKOVÝ ZOSTATOK V BANKÁCH A HOTOVOSTI
        // Prepočítavame každý účet podľa jeho reálneho kurzu v DB
        $bankBalance = Account::with('currency')->get()->sum(function ($account) {
            $rate = $account->currency?->exchange_rate > 0 ? (float)$account->currency->exchange_rate : 1.0;
            return (float)$account->balance / $rate;
        });

        // 2. TRHOVÁ HODNOTA INVESTÍCIÍ
        // Využívame náš inteligentný model Investment, ktorý už v sebe má výpočet v EUR
        $investmentValue = Investment::with(['transactions', 'currency'])
            ->where('is_archived', false) // Počítame len to, čo reálne vlastníme
            ->get()
            ->sum('current_market_value_eur');

        $totalNetWorth = $bankBalance + $investmentValue;

        // 3. VÝPOČET POMEROV (Percentá)
        $bankPercent = $totalNetWorth > 0 ? ($bankBalance / $totalNetWorth) * 100 : 0;
        $investPercent = $totalNetWorth > 0 ? ($investmentValue / $totalNetWorth) * 100 : 0;

        return [
            // HLAVNÁ KARTA: TOTAL NET WORTH
            Stat::make('Čistý majetok (Net Worth)', number_format($totalNetWorth, 2, ',', ' ') . ' €')
                ->description('Peniaze v bankách + Trhová hodnota portfólia')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            // KARTA: CASH POMER
            Stat::make('Likvidná hotovosť', number_format($bankPercent, 1) . ' %')
                ->description(number_format($bankBalance, 2, ',', ' ') . ' € na účtoch')
                ->icon('heroicon-m-banknotes')
                ->color('info'),

            // KARTA: INVESTIČNÝ POMER
            Stat::make('Pomer v investíciách', number_format($investPercent, 1) . ' %')
                ->description(number_format($investmentValue, 2, ',', ' ') . ' € na burze')
                ->icon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}