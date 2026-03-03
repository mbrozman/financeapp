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
        $symbol = $record->currency?->symbol ?? '$';

        // POUŽÍVAME UŽ LEN ATRIBÚTY Z MODELU
        $gainBase = $record->is_archived
            ? (float)$record->total_sales_base - (float)$record->total_invested_base
            : (float)$record->current_market_value_base - (float)$record->total_invested_base;

        $investedBase = (float)$record->total_invested_base;
        $gainPercent = $investedBase > 0 ? ($gainBase / $investedBase) * 100 : 0;

        $isProfit = $gainBase >= 0;
        $color = $isProfit ? 'success' : 'danger';

        return [
            Stat::make("Výsledok ({$symbol})", number_format($gainBase, 2, ',', ' ') . " {$symbol}")
                ->description($record->is_archived ? 'Realizovaný konečný zisk' : 'Aktuálny nerealizovaný stav')
                ->color($color),

            Stat::make("Výkonnosť pozície", number_format($gainPercent, 2, ',', ' ') . ' %')
                ->description('Percentuálne zhodnotenie kapitálu')
                ->color($color),
        ];
    }
}
