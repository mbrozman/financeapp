<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

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

        // 1. ZABEZPEČENIE DÁT (Eager Loading)
        // Vynútime načítanie transakcií, aby výpočty v modeli neboli nulové
        if (!$this->record->relationLoaded('transactions')) {
            $this->record->load(['transactions', 'currency']);
        }

        $record = $this->record;
        $symbol = $record->currency?->symbol ?? '$';

        // 2. INICIALIZÁCIA CEZ BIGDECIMAL (Všetko ťaháme ako stringy z modelu)
        $investedBase = BigDecimal::of($record->total_invested_base ?? 0);
        
        $currentValueBase = BigDecimal::of(
            $record->is_archived 
                ? ($record->total_sales_base ?? 0) 
                : ($record->current_market_value_base ?? 0)
        );

        // 3. PRESNÁ MATEMATIKA
        // Zisk = Aktuálna hodnota - Investované
        $gainBase = $currentValueBase->minus($investedBase);

        // Výnos % = (Zisk / Investované) * 100
        $gainPercent = $investedBase->isGreaterThan(0)
            ? $gainBase->dividedBy($investedBase, 4, RoundingMode::HALF_UP)->multipliedBy(100)
            : BigDecimal::zero();

        // 4. LOGIKA ZOBRAZENIA
        $isProfit = $gainBase->isGreaterThanOrEqualTo(0);
        $color = $isProfit ? 'success' : 'danger';
        $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            // KARTA 1: ČISTÝ VÝSLEDOK V USD/mene aktíva
            Stat::make(
                "Výsledok ({$symbol})", 
                number_format($gainBase->toFloat(), 2, ',', ' ') . " {$symbol}"
            )
                ->description($record->is_archived ? 'Konečný realizovaný zisk' : 'Aktuálny nerealizovaný stav')
                ->descriptionIcon($icon)
                ->color($color)
                ->extraAttributes([
                    'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
                ]),

            // KARTA 2: VÝKONNOSŤ V %
            Stat::make(
                "Výkonnosť pozície", 
                number_format($gainPercent->toScale(2, RoundingMode::HALF_UP)->toFloat(), 2, ',', ' ') . ' %'
            )
                ->description('Percentuálne zhodnotenie')
                ->color($color)
                ->extraAttributes([
                    'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
                ]),
        ];
    }
}