<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use App\Enums\TransactionType;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Services\CurrencyService;

class InvestmentProfitStats extends BaseWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 1;

    // Zmeníme počet stĺpcov na 1, aby boli karty v stĺpci vedľa grafu
    protected function getColumns(): int
    {
        return 1;
    }

    protected function getStats(): array
    {
        if (!$this->record) return [];

        // 1. ZABEZPEČENIE DÁT (Eager Loading)
        if (!$this->record->relationLoaded('transactions')) {
            $this->record->load(['transactions', 'currency']);
        }

        $record = $this->record;
        $isEur = request()->query('currency') === 'EUR';
        $symbol = $isEur ? '€' : ($record->currency?->symbol ?? '$');

        // 2. INICIALIZÁCIA CEZ BIGDECIMAL (Zohľadníme prepínač meny)
        if ($isEur) {
            $investedBase = BigDecimal::of($record->total_invested_eur ?? 0);
            $currentValueBase = BigDecimal::of($record->current_market_value_eur ?? 0);
            $gainBase = BigDecimal::of($record->gain_eur ?? 0);
        } else {
            $investedBase = BigDecimal::of($record->total_invested_base ?? 0);
            $currentValueBase = BigDecimal::of(
                $record->is_archived 
                    ? ($record->total_sales_base ?? 0) 
                    : ($record->current_market_value_base ?? 0)
            );
            $gainBase = $currentValueBase->minus($investedBase);
        }

        // 3. VÝPOČET PERCENT (Tie sú rovnaké pre obe meny, ak vychádzame z rovnakého základu)
        $gainPercent = $investedBase->isGreaterThan(0)
            ? $gainBase->dividedBy($investedBase, 4, RoundingMode::HALF_UP)->multipliedBy(100)
            : BigDecimal::zero();

        // 4. VÝPOČET POPLATKOV A DIVIDEND (S konverziou do EUR ak treba)
        $totalFees = BigDecimal::of(0);
        $totalDividends = BigDecimal::of(0);

        foreach ($record->transactions as $tx) {
            $comm = BigDecimal::of($tx->commission ?? 0);
            
            if ($tx->type === TransactionType::DIVIDEND) {
                $divVal = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
                if ($isEur) {
                    $totalDividends = $totalDividends->plus(CurrencyService::convertToEur((string)$divVal, $tx->currency_id, $tx->exchange_rate));
                } else {
                    $totalDividends = $totalDividends->plus($divVal);
                }
            }

            if ($comm->isGreaterThan(0)) {
                if ($isEur) {
                    $totalFees = $totalFees->plus(CurrencyService::convertToEur((string)$comm, $tx->currency_id, $tx->exchange_rate));
                } else {
                    $totalFees = $totalFees->plus($comm);
                }
            }
        }

        // 5. LOGIKA ZOBRAZENIA
        $isProfit = $gainBase->isGreaterThanOrEqualTo(0);
        $color = $isProfit ? 'success' : 'danger';
        $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            // KARTA 1: ČISTÝ VÝSLEDOK (P/L)
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

            // KARTA 2: VÝKONNOSŤ (%)
            Stat::make(
                "Výkonnosť pozície", 
                number_format($gainPercent->toScale(2, RoundingMode::HALF_UP)->toFloat(), 2, ',', ' ') . ' %'
            )
                ->description('Celkové zhodnotenie kapitálu')
                ->color($color)
                ->extraAttributes([
                    'class' => 'border-l-4 ' . ($isProfit ? 'border-green-500' : 'border-red-500'),
                ]),

            // KARTA 3: NÁKLADY A DIVIDENDY (Nové)
            Stat::make(
                "Poplatky / Dividendy", 
                number_format($totalFees->toFloat(), 2, ',', ' ') . " / " . number_format($totalDividends->toFloat(), 2, ',', ' ') . " {$symbol}"
            )
                ->description('Suma poplatkov vs. prijatý pasívny príjem')
                ->icon('heroicon-m-banknotes')
                ->color('gray')
                ->extraAttributes([
                    'class' => 'border-l-4 border-gray-400',
                ]),
        ];
    }
}