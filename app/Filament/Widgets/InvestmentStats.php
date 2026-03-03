<?php

namespace App\Filament\Widgets;

use App\Models\Investment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InvestmentStats extends BaseWidget
{
    // Zoradenie: StatsOverview (Banky) je 1, toto bude 2
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // 1. EAGER LOADING (Kritické pre výkon)
        // Stiahneme len nearchivované investície a rovno k nim pribalíme transakcie a meny
        $investments = Investment::with(['transactions', 'currency'])
            ->where('is_archived', false)
            ->get();

        // 2. VÝPOČET POMOCOU MODELU
        // Keďže už máme v modeli Investment.php atribúty total_invested_eur 
        // a current_market_value_eur, stačí ich jednoducho sčítať.
        
        $totalInvested = $investments->sum('total_invested_eur');
        $currentValue = $investments->sum('current_market_value_eur');

        $profitEur = $currentValue - $totalInvested;
        $profitPercent = $totalInvested > 0 ? ($profitEur / $totalInvested) * 100 : 0;

        // 3. LOGIKA FARIEB
        $isProfit = $profitEur >= 0;
        $color = $isProfit ? 'success' : 'danger';
        $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            // KARTA 1: KOĽKO SI VLOŽIL
            Stat::make('Investovaný kapitál', number_format($totalInvested, 2, ',', ' ') . ' €')
                ->description('Suma nákupov v tvojom portfóliu')
                ->icon('heroicon-m-banknotes'),

            // KARTA 2: KOĽKO TO MÁ HODNOTU DNES
            Stat::make('Aktuálna hodnota portfólia', number_format($currentValue, 2, ',', ' ') . ' €')
                ->description('Trhová cena prepočítaná na EUR')
                ->icon('heroicon-m-chart-bar-square')
                ->color('info'),

            // KARTA 3: CELKOVÝ NEREALIZOVANÝ ZISK
            Stat::make('Celkový zisk / strata', number_format($profitEur, 2, ',', ' ') . ' €')
                ->description(number_format($profitPercent, 2, ',', ' ') . ' % výnos')
                ->descriptionIcon($icon)
                ->color($color),
        ];
    }
}