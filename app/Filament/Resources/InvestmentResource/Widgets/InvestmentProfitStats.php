<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

class InvestmentProfitStats extends BaseWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 1;

    protected function getColumns(): int
    {
        return 1;
    }

    protected function getStats(): array
{
    if (!$this->record) return [];
    $this->record->loadMissing(['transactions', 'currency']);

    $record = $this->record;

    $investedBase = (float)$record->total_invested_base;
    $currentValueBase = $record->is_archived ? (float)$record->total_sales_base : (float)$record->current_market_value_base;
    
    $symbol = $record->currency?->symbol ?? '$';

    // 1. ZÍSKAME HODNOTY (Ošetrené pretypovaním)
    $investedBase = (float)$record->total_invested_base;
    $currentValueBase = $record->is_archived 
        ? (float)$record->total_sales_base 
        : (float)$record->current_market_value_base;

    // 2. VÝPOČET ZISKU
    $gainBase = $currentValueBase - $investedBase;

    // 3. OCHRANA PROTI DELENIU NULOU (To bol dôvod prázdnych widgetov!)
    // Ak je investedBase 0 alebo menej (napr. pri chybných dátach), percento bude 0
    $gainPercent = ($investedBase > 0) ? ($gainBase / $investedBase) * 100 : 0;

    $isProfit = $gainBase >= 0;
    $color = $isProfit ? 'success' : 'danger';
    $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

    return [
        Stat::make("Výsledok ({$symbol})", number_format($gainBase, 2, ',', ' ') . " {$symbol}")
            ->description($record->is_archived ? 'Konečný realizovaný zisk' : 'Aktuálny nerealizovaný stav')
            ->descriptionIcon($icon)
            ->color($color)
            ->extraAttributes([
                'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
            ]),

        Stat::make("Výkonnosť pozície", number_format($gainPercent, 2, ',', ' ') . ' %')
            ->description('Percentuálne zhodnotenie')
            ->color($color)
            ->extraAttributes([
                'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
            ]),
    ];
}
}
