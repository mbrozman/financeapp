<?php

namespace App\Filament\Widgets;

use App\Services\DashboardFinanceService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetWorthOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        $stats = app(DashboardFinanceService::class)->getLiquidityStats((int) auth()->id());
        $totalLiquidity = $stats['total_liquidity'];
        $totalBank = $stats['total_bank'];
        $totalCash = $stats['total_cash'];

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
