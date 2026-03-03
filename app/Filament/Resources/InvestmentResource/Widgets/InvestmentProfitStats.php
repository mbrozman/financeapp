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

        // TENTO RIADOK JE KĽÚČOVÝ: Vynútime načítanie transakcií z DB do pamäte
        $this->record->load(['transactions', 'currency']);

        $record = $this->record;
        $symbol = $record->currency?->symbol ?? '$';

        // Výpočty v Base mene
        $investedBase = (float)$record->total_invested_base;
        $currentValueBase = (float)$record->current_market_value_base;

        // Ak je akcia v archive, prepneme na tržby
        if ($record->is_archived) {
            $currentValueBase = (float)$record->total_sales_base;
        }

        $gainBase = $currentValueBase - $investedBase;
        $gainPercent = ($investedBase > 0) ? ($gainBase / $investedBase) * 100 : 0;

        return [
            Stat::make("Výsledok ({$symbol})", number_format($gainBase, 2, ',', ' ') . " {$symbol}")
                ->color($gainBase >= 0 ? 'success' : 'danger'),

            Stat::make("Výkonnosť pozície", number_format($gainPercent, 2, ',', ' ') . ' %')
                ->color($gainBase >= 0 ? 'success' : 'danger'),
        ];
    }
}
