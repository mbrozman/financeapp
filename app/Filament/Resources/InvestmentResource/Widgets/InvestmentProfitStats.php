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

        $record = $this->record;
        
        // 1. ZÍSKAME SYMBOL (USD/EUR...)
        $symbol = $record->currency?->symbol ?? '$';

        // 2. LOGIKA VÝPOČTOV V DOMOVSKEJ MENE (Base)
        $investedBase = (float)$record->total_invested_base;
        
        if ($record->is_archived) {
            $currentValueBase = (float)$record->total_sales_base;
            $labelPrefix = 'Realizovaný';
            $desc = 'Konečný výsledok v mene aktíva';
        } else {
            $currentValueBase = (float)$record->current_market_value_base;
            $labelPrefix = 'Nerealizovaný';
            $desc = 'Aktuálny stav v mene aktíva';
        }

        $gainBase = $currentValueBase - $investedBase;
        $gainPercent = $investedBase > 0 ? ($gainBase / $investedBase) * 100 : 0;
        
        $isProfit = $gainBase >= 0;
        $color = $isProfit ? 'success' : 'danger';
        $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            // KARTA 1: ZISK V DOMOVSKEJ MENE
            Stat::make("{$labelPrefix} výsledok ({$symbol})", number_format($gainBase, 2, ',', ' ') . " {$symbol}")
                ->description($desc)
                ->descriptionIcon($icon)
                ->color($color)
                ->extraAttributes([
                    'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
                ]),

            // KARTA 2: VÝNOS V % (Počítaný z Base meny)
            Stat::make("{$labelPrefix} výkonnosť (%)", number_format($gainPercent, 2, ',', ' ') . ' %')
                ->description('Percentuálna zmena hodnoty')
                ->color($color)
                ->extraAttributes([
                    'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
                ]),
        ];
    }
}