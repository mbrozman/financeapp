<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Transaction;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;
use Brick\Math\BigDecimal;

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

        // 1. Banky a Hotovosť (bank, cash, reserve) - Len AKTÍVNE
        $liquidityAccounts = Account::with('currency')
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->whereIn('type', ['bank', 'cash', 'reserve'])
            ->get();
        
        $totalBankCash = BigDecimal::zero();
        foreach ($liquidityAccounts as $account) {
            $valConverted = \App\Services\CurrencyService::convert(
                $account->balance, 
                $account->currency_id, 
                $targetCurrencyId
            );
            $totalBankCash = $totalBankCash->plus($valConverted);
        }

        // 2. Brokerské účty (investment - cash at brokers) - Len AKTÍVNE
        $brokerAccounts = Account::with('currency')
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->where('type', 'investment')
            ->get();
            
        $totalBrokerCash = BigDecimal::zero();
        foreach ($brokerAccounts as $account) {
            $valConverted = \App\Services\CurrencyService::convert(
                $account->balance, 
                $account->currency_id, 
                $targetCurrencyId
            );
            $totalBrokerCash = $totalBrokerCash->plus($valConverted);
        }

        // 3. Investičné pozície (Market Value of active stocks)
        // Eager loadujeme menové relácie pre presnosť
        $investments = \App\Models\Investment::with('currency')->where('user_id', auth()->id())->get();
        $totalPositionsValue = BigDecimal::zero();
        foreach ($investments as $inv) {
            $totalPositionsValue = $totalPositionsValue->plus($inv->getCurrentValueForCurrency($currencyCode));
        }

        // 4. Celkový majetok = Všetko dokopy
        $totalNetWorth = $totalBankCash->plus($totalBrokerCash)->plus($totalPositionsValue);

        return [
            Stat::make('Celkový majetok', number_format((float)(string)$totalNetWorth, 2, ',', ' ') . ' ' . $symbol)
                ->description('Sumár kompletného bohatstva')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('success'),

            Stat::make('Banky a Hotovosť', number_format((float)(string)$totalBankCash, 2, ',', ' ') . ' ' . $symbol)
                ->description('Bežné účty, úspory a keš')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Peniaze u Brokerov', number_format((float)(string)$totalBrokerCash, 2, ',', ' ') . ' ' . $symbol)
                ->description('Neinvestovaná hotovosť na invest. účtoch')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('primary'),
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
