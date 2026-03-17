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
    protected function getCurrency(): string
    {
        return session('global_currency', 'EUR');
    }

    protected function getStats(): array
    {
        $investments = Investment::with(['transactions', 'currency'])->where('user_id', auth()->id())->get();

        $totalValue = BigDecimal::zero();
        $totalInvested = BigDecimal::zero();
        $totalGain = BigDecimal::zero();

        $targetCurrency = \App\Models\Currency::where('code', $this->getCurrency())->first();
        $targetCurrencyId = $targetCurrency?->id; // Ak null, CurrencyService::convert použije EUR ako fallback (ak mu pošleme null cieľ)
        if (!$targetCurrencyId && $this->getCurrency() === 'EUR') {
            // Skúsime nájsť EUR v DB pre istotu, ak nie je, convert(..., null) by malo fungovať ako EUR
            $targetCurrencyId = \App\Models\Currency::where('code', 'EUR')->first()?->id;
        }

        foreach($investments as $inv) {
            // 1. Hodnota (Market Value / Sales)
            $totalValue = $totalValue->plus($inv->getCurrentValueForCurrency($this->getCurrency()));

            // 2. Investované (Cost Basis)
            $totalInvested = $totalInvested->plus($inv->getInvestedForCurrency($this->getCurrency()));

            // 3. Zisk (P/L)
            $totalGain = $totalGain->plus($inv->getGainForCurrency($this->getCurrency()));
        }

        $symbol = match($this->getCurrency()) {
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
        return true;
    }
}
