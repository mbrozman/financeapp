<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Transaction;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends BaseWidget
{
    // Ako často sa má dashboard automaticky obnovovať (každých 30 sekúnd)
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;
    protected function getCurrency(): string
    {
        return session('global_currency', 'EUR');
    }

    protected function getCurrencySymbol(): string
    {
        return match($this->getCurrency()) {
            'USD' => '$',
            'CZK' => 'Kč',
            'GBP' => '£',
            default => '€'
        };
    }

    protected function getStats(): array
    {
        $currencyCode = $this->getCurrency();
        $symbol = $this->getCurrencySymbol();
        $targetCurrency = \App\Models\Currency::where('code', $currencyCode)->first();
        $targetCurrencyId = $targetCurrency?->id;

        // 1. Likvidita (Bank + Cash)
        $accounts = Account::with('currency')->where('user_id', auth()->id())->get();
        $totalLiquidity = 0.0;

        foreach ($accounts as $account) {
            $valConverted = \App\Services\CurrencyService::convert(
                $account->balance, 
                $account->currency_id, 
                $targetCurrencyId
            );
            $totalLiquidity += (float) $valConverted;
        }

        // 2. Investície (Market Value)
        $investments = \App\Models\Investment::where('user_id', auth()->id())->get();
        $totalInvestments = 0.0;
        foreach ($investments as $inv) {
            $totalInvestments += (float) $inv->getCurrentValueForCurrency($currencyCode);
        }

        // 3. Celkový majetok
        $totalNetWorth = $totalLiquidity + $totalInvestments;

        return [
            Stat::make('Celkový majetok', number_format($totalNetWorth, 2, ',', ' ') . ' ' . $symbol)
                ->description('Likvidita + Trhová cena investícií (' . $currencyCode . ')')
                ->descriptionIcon('heroicon-m-scale')
                ->color('success'),

            Stat::make('Likvidita', number_format($totalLiquidity, 2, ',', ' ') . ' ' . $symbol)
                ->description('Bankové účty + hotovosť (' . $currencyCode . ')')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Investície', number_format($totalInvestments, 2, ',', ' ') . ' ' . $symbol)
                ->description('Aktuálna hodnota portfólia (' . $currencyCode . ')')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
