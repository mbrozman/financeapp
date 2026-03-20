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
    protected function getStats(): array
    {
        // 1. Likvidita (Bank + Cash)
        $accounts = Account::with('currency')->where('user_id', auth()->id())->get();
        $totalLiquidity = 0.0;
        $totalBank = 0.0;
        $totalCash = 0.0;

        foreach ($accounts as $account) {
            $rate = $account->currency->exchange_rate ?: 1;
            $valEur = $account->balance * $rate;
            $totalLiquidity += $valEur;
            
            if ($account->type === 'bank') $totalBank += $valEur;
            if ($account->type === 'cash') $totalCash += $valEur;
        }

        // 2. Investície (Market Value)
        $investments = \App\Models\Investment::where('user_id', auth()->id())->get();
        $totalInvestments = 0.0;
        foreach ($investments as $inv) {
            $totalInvestments += (float) $inv->current_market_value_eur;
        }

        // 3. Celkový majetok
        $totalNetWorth = $totalLiquidity + $totalInvestments;

        return [
            Stat::make('Celkový majetok', number_format($totalNetWorth, 2, ',', ' ') . ' €')
                ->description('Likvidita + Trhová cena investícií')
                ->descriptionIcon('heroicon-m-scale')
                ->color('success'),

            Stat::make('Likvidita', number_format($totalLiquidity, 2, ',', ' ') . ' €')
                ->description('Bankové účty + hotovosť')
                ->descriptionIcon('heroicon-m-wallet')
                ->color('info'),

            Stat::make('Investície', number_format($totalInvestments, 2, ',', ' ') . ' €')
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
