<?php

namespace App\Filament\Widgets;

use App\Models\Investment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class InvestmentStats extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        // 1. EAGER LOADING
        $investments = Investment::with(['transactions', 'currency'])
            ->where('is_archived', false)
            ->get();

        // 2. INICIALIZÁCIA PRESNEJ MATEMATIKY
        $totalInvested = BigDecimal::of(0);
        $currentValue = BigDecimal::of(0);

        // 3. RUČNÉ SČÍTANIE (Namiesto nepresného sum())
        foreach ($investments as $investment) {
            // Sčítavame stringy, ktoré vracajú naše atribúty v modeli
            $totalInvested = $totalInvested->plus($investment->total_invested_eur ?? 0);
            $currentValue = $currentValue->plus($investment->current_market_value_eur ?? 0);
        }

        // 4. VÝPOČET ZISKU A PERCENT
        $profitEur = $currentValue->minus($totalInvested);

        $profitPercent = $totalInvested->isGreaterThan(0)
            ? $profitEur->dividedBy($totalInvested, 4, RoundingMode::HALF_UP)->multipliedBy(100)
            : BigDecimal::zero();

        // 5. LOGIKA FARIEB A IKON
        $isProfit = $profitEur->isGreaterThanOrEqualTo(0);
        $color = $isProfit ? 'success' : 'danger';
        $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            // KARTA 1: INVESTOVANÝ KAPITÁL
            Stat::make('Investovaný kapitál', number_format($totalInvested->toFloat(), 2, ',', ' ') . ' €')
                ->description('Suma nákupov v tvojom portfóliu')
                ->icon('heroicon-m-banknotes'),

            // KARTA 2: AKTUÁLNA HODNOTA
            Stat::make('Aktuálna hodnota portfólia', number_format($currentValue->toFloat(), 2, ',', ' ') . ' €')
                ->description('Trhová cena prepočítaná na EUR')
                ->icon('heroicon-m-chart-bar-square')
                ->color('info'),

            // KARTA 3: ZISK / STRATA
            Stat::make('Celkový zisk / strata', number_format($profitEur->toFloat(), 2, ',', ' ') . ' €')
                ->description(number_format($profitPercent->toFloat(), 2, ',', ' ') . ' % výnos')
                ->descriptionIcon($icon)
                ->color($color),
        ];
    }
    public static function canView(): bool
    {
        return false; // Toto skryje widget z Dashboardu
    }
}
