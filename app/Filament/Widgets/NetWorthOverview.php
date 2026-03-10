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
        // 1. Hotovosť: súčet bankových účtov a hotovosti (konvertované do EUR)
        $cashAccounts = Account::with('currency')
            ->whereIn('type', ['bank', 'cash'])
            ->get();

        $totalCash = 0.0;
        foreach ($cashAccounts as $account) {
            $rate = $account->currency->exchange_rate ?: 1;
            $totalCash += $account->balance / $rate;
        }

        // 2. Investičná trhová hodnota (aktuálna)
        $investments = Investment::with(['transactions', 'currency'])
            ->where('user_id', auth()->id())
            ->get();

        $totalMarketValue = BigDecimal::zero();
        foreach ($investments as $inv) {
            $valBase = $inv->is_archived ? $inv->total_sales_base : $inv->current_market_value_base;
            $valEur = CurrencyService::convertToEur((string)$valBase, $inv->currency_id);
            $totalMarketValue = $totalMarketValue->plus(BigDecimal::of($valEur));
        }

        $totalMarketValueFloat = (float)(string)$totalMarketValue;

        // 3. Čisté majetok = hotovosť + investičná hodnota
        $netWorth = $totalCash + $totalMarketValueFloat;

        return [
            Stat::make('Čisté majetok', number_format($netWorth, 2, ',', ' ') . ' €')
                ->description('Hotovosť + trhová hodnota investícií')
                ->descriptionIcon('heroicon-m-scale')
                ->color('success'),

            Stat::make('Hotovosť', number_format($totalCash, 2, ',', ' ') . ' €')
                ->description('Bankové účty a fyzická hotovosť')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Investície (trhová cena)', number_format($totalMarketValueFloat, 2, ',', ' ') . ' €')
                ->description('Aktuálna hodnota portfólia')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('warning'),
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
