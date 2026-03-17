<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Investment;
use App\Services\CurrencyService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class NetWorthOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        // 1. Bankové účty
        $bankAccounts = Account::with('currency')
            ->where('type', 'bank')
            ->get();

        $totalBank = 0.0;
        foreach ($bankAccounts as $account) {
            $rate = $account->currency->exchange_rate ?: 1;
            $totalBank += $account->balance * $rate;
        }

        // 2. Hotovosť
        $cashAccounts = Account::with('currency')
            ->where('type', 'cash')
            ->get();

        $totalCash = 0.0;
        foreach ($cashAccounts as $account) {
            $rate = $account->currency->exchange_rate ?: 1;
            $totalCash += $account->balance * $rate;
        }

        // 3. Celková likvidita
        $totalLiquidity = $totalBank + $totalCash;

        return [
            Stat::make('Celková likvidita', number_format($totalLiquidity, 2, ',', ' ') . ' €')
                ->description('Bankové účty + hotovosť')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('success'),

            Stat::make('Bankové účty', number_format($totalBank, 2, ',', ' ') . ' €')
                ->description('Suma na bežných a sporiciach účtoch')
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),

            Stat::make('Hotovosť', number_format($totalCash, 2, ',', ' ') . ' €')
                ->description('Fyzická hotovosť a drobné')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
