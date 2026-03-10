<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Investment;
use Livewire\Attributes\Reactive;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use App\Services\PortfolioPerformanceService;

class InvestmentKpiOverview extends BaseWidget
{
    #[Reactive]
    public string $currency = 'EUR';

    protected function getStats(): array
    {
        $investments = Investment::with(['transactions', 'currency'])->where('user_id', auth()->id())->get();

        $totalValue = BigDecimal::zero();
        $totalInvested = BigDecimal::zero();
        $totalGain = BigDecimal::zero();

        $targetRate = CurrencyService::getRate($this->currency);
        $targetRateBD = BigDecimal::of($targetRate);

        foreach($investments as $inv) {
            // Hodnota zakladna
            $valBase = $inv->is_archived ? $inv->total_sales_base : $inv->current_market_value_base;
            $valEur = CurrencyService::convertToEur((string)$valBase, $inv->currency_id);
            $valTarget = BigDecimal::of($valEur)->multipliedBy($targetRateBD);
            $totalValue = $totalValue->plus($valTarget);

            // Investovane
            $invBase = $inv->total_invested_base;
            $invEur = CurrencyService::convertToEur((string)$invBase, $inv->currency_id);
            $invTarget = BigDecimal::of($invEur)->multipliedBy($targetRateBD);
            $totalInvested = $totalInvested->plus($invTarget);

            // Zisk
            $gainBase = $inv->total_gain_base;
            $gainEur = CurrencyService::convertToEur((string)$gainBase, $inv->currency_id);
            $gainTarget = BigDecimal::of($gainEur)->multipliedBy($targetRateBD);
            $totalGain = $totalGain->plus($gainTarget);
        }

        $symbol = match($this->currency) {
            'USD' => '$',
            'CZK' => 'Kč',
            'GBP' => '£',
            default => '€'
        };

        $percent = $totalInvested->isGreaterThan(0) 
            ? $totalGain->dividedBy($totalInvested, 4, RoundingMode::HALF_UP)->multipliedBy(100) 
            : BigDecimal::zero();

        $gainStr = number_format((float)(string)$totalGain, 2, ',', ' ');
        $percentStr = number_format((float)(string)$percent, 2, ',', ' ');
        $gainPrefix = $totalGain->isGreaterThan(0) ? '+' : '';

        return [
            Stat::make('Celková hodnota (Net Value)', number_format((float)(string)$totalValue, 2, ',', ' ') . ' ' . $symbol)
                ->description('Aktuálna hodnota portfólia')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
                
            Stat::make('Celkovo Vložené', number_format((float)(string)$totalInvested, 2, ',', ' ') . ' ' . $symbol)
                ->description('Súčet tvojich vkladov bez zisku/straty')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('gray'),
                
            Stat::make('Nerealizovaný Zisk / Strata', $gainPrefix . $gainStr . ' ' . $symbol)
                ->description($gainPrefix . $percentStr . ' % z vkladov')
                ->descriptionIcon($totalGain->isGreaterThan(0) ? 'heroicon-m-arrow-trending-up' : ($totalGain->isLessThan(0) ? 'heroicon-m-arrow-trending-down' : 'heroicon-m-minus'))
                ->color($totalGain->isGreaterThan(0) ? 'success' : ($totalGain->isLessThan(0) ? 'danger' : 'gray')),
        ];
    }
    public static function canView(): bool
    {
        return false;
    }
}
