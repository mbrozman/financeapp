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
        $currencyCode = $record->currency?->code;
        $symbol = $record->currency?->symbol ?? '$';

        // 3. VÝPOČET HODNÔT CEZ MODEL
        $investedTarget = BigDecimal::of($record->total_invested_base);
        $gainBase = BigDecimal::of($record->total_gain_base);

        // 4. VÝPOČET PERCENT
        $gainPercent = $investedTarget->isGreaterThan(0)
            ? $gainBase->dividedBy($investedTarget, 4, RoundingMode::HALF_UP)->multipliedBy(100)
            : BigDecimal::zero();

        // 5. VÝPOČET POPLATKOV A DIVIDEND
        $totalFees = BigDecimal::of(0);
        $totalDividends = BigDecimal::of(0);

        foreach ($record->transactions as $tx) {
            if ($tx->type === TransactionType::DIVIDEND) {
                $divVal = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
                $totalDividends = $totalDividends->plus($divVal);
            }

            if (BigDecimal::of($tx->commission ?? 0)->isGreaterThan(0)) {
                $totalFees = $totalFees->plus($tx->commission);
            }
        }

        // 5. LOGIKA ZOBRAZENIA
        $isProfit = $gainBase->isGreaterThanOrEqualTo(0);
        $color = $isProfit ? 'success' : 'danger';
        $icon = $isProfit ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            // KARTA 1: ČISTÝ VÝSLEDOK (P/L)
            Stat::make(
                "Výsledok ($symbol)", 
                number_format($gainBase->toFloat(), 2, ',', ' ') . " $symbol"
            )
                ->description($record->is_archived ? 'Konečný realizovaný zisk' : 'Aktuálny nerealizovaný stav')
                ->descriptionIcon($icon)
                ->color($color),

            // KARTA 2: VÝKONNOSŤ (%)
            Stat::make(
                "Výkonnosť pozície", 
                number_format($gainPercent->toScale(2, RoundingMode::HALF_UP)->toFloat(), 2, ',', ' ') . ' %'
            )
                ->description('Celkové zhodnotenie kapitálu')
                ->color($color),

            // KARTA 3: NÁKLADY A DIVIDENDY
            Stat::make(
                "Poplatky / Dividendy", 
                number_format($totalFees->toFloat(), 2, ',', ' ') . " / " . number_format($totalDividends->toFloat(), 2, ',', ' ') . " $symbol"
            )
                ->description('Suma poplatkov vs. prijatý pasívny príjem')
                ->icon('heroicon-m-banknotes')
                ->color('gray'),
        ];
    }
}