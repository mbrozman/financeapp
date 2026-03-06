<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Investment;
use App\Services\CurrencyService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Brick\Math\BigDecimal;

class NetWorthOverview extends BaseWidget
{
    protected static ?int $sort = 0;
    protected  ?string $heading = 'Prehľad majetku a likvidity';

    protected function getStats(): array
    {
        // 1. VÝPOČET LIKVIDNEJ HOTOVOSTI (Banka + Hotovosť v šuflíku)
        $liquidCashBD = BigDecimal::of(0);
        $bankAccounts = Account::with('currency')->whereIn('type', ['bank', 'cash'])->get();

        foreach ($bankAccounts as $account) {
            $converted = CurrencyService::convertToEur((string)$account->balance, $account->currency_id);
            $liquidCashBD = $liquidCashBD->plus($converted);
        }

        // 2. VÝPOČET INVESTIČNÉHO MAJETKU (Aktuálna trhová hodnota v EUR)
        $investmentsValueBD = BigDecimal::of(0);
        $activeInvestments = Investment::with(['transactions', 'currency'])->where('is_archived', false)->get();

        foreach ($activeInvestments as $investment) {
            $investmentsValueBD = $investmentsValueBD->plus($investment->current_market_value_eur);
        }

        // 3. CELKOVÝ ČISTÝ MAJETOK (Net Worth)
        $totalNetWorthBD = $liquidCashBD->plus($investmentsValueBD);

        return [
            // KARTA 1: CELKOVÝ MAJETOK
            Stat::make('Celkový čistý majetok', number_format($totalNetWorthBD->toFloat(), 2, ',', ' ') . ' €')
                ->description('Kompletné financie ')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('success'),

            // KARTA 2: PENIAZE K DISPOZÍCII (Liquid)
            Stat::make('Dostupná hotovosť', number_format($liquidCashBD->toFloat(), 2, ',', ' ') . ' €')
                ->description('Peniaze v bankách a hotovosti')
                ->icon('heroicon-m-banknotes')
                ->color('info'),

            // KARTA 3: MAJETOK V INVESTÍCIÁCH
            Stat::make('Investičné portfólio', number_format($investmentsValueBD->toFloat(), 2, ',', ' ') . ' €')
                ->description('Aktuálna investovaná hodnota')
                ->icon('heroicon-m-chart-bar')
                ->color('warning'),
        ];
    }
}
