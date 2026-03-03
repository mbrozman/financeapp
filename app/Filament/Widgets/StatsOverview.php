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
        // 1. VÝPOČET CELKOVÉHO MAJETKU (Net Worth)
        // Prepočítavame každý účet podľa jeho kurzu do EUR (naša základná mena)
        $totalNetWorth = Account::with('currency')->get()->sum(function ($account) {
            return $account->balance / ($account->currency->exchange_rate ?: 1);
        });

        // 2. PRÍJMY ZA AKTUÁLNY MESIAC
        $monthlyIncome = Transaction::where('type', 'income')
            ->whereMonth('transaction_date', Carbon::now()->month)
            ->whereYear('transaction_date', Carbon::now()->year)
            ->sum('amount');

        // 3. VÝDAVKY ZA AKTUÁLNY MESIAC
        $monthlyExpenses = Transaction::where('type', 'expense')
            ->whereMonth('transaction_date', Carbon::now()->month)
            ->whereYear('transaction_date', Carbon::now()->year)
            ->sum('amount');

        // 4. PRIEMERNÉ MESAČNÉ VÝDAVKY (za posledné 3 mesiace)
        // Získame sumu výdavkov za 90 dní a vydelíme ju tromi
        $expensesLastThreeMonths = Transaction::where('type', 'expense')
            ->where('transaction_date', '>=', now()->subDays(90))
            ->sum('amount');

        $averageMonthlyExpense = abs($expensesLastThreeMonths) / 3;

        // 5. VÝPOČET REZERVY (Počet mesiacov)
        // Ak sú výdavky 0, rezerva je technicky "nekonečná", tak to ošetríme
        $financialBufferMonths = $averageMonthlyExpense > 0
            ? $totalNetWorth / $averageMonthlyExpense
            : 0;

        // Určíme farbu podľa stavu rezervy
        $bufferColor = match (true) {
            $financialBufferMonths >= 6 => 'success', // 6+ mesiacov je bezpečné
            $financialBufferMonths >= 3 => 'warning', // 3-6 mesiacov je fajn
            default => 'danger',                      // menej ako 3 je riziko
        };

        return [
            Stat::make('Celkový majetkok (v EUR)', number_format($totalNetWorth, 2) . ' €')
                ->description('Súčet všetkých účtov a investícií')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Príjmy tento mesiac', number_format($monthlyIncome, 2) . ' €')
                ->description('Peniaze pripísané k dnešnému dňu')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17]) // Toto neskôr napojíme na reálne dáta
                ->color('success'),

            Stat::make('Výdavky tento mesiac', number_format(abs($monthlyExpenses), 2) . ' €')
                ->description('Peniaze odoslané z účtov')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->chart([15, 4, 10, 2, 12, 4, 11])
                ->color('danger'),
            Stat::make('Finančná rezerva', number_format($financialBufferMonths, 1) . ' mes.')
                ->description($financialBufferMonths >= 6 ? 'Ste v bezpečí' : 'Odporúča sa aspoň 6 mesiacov')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($bufferColor),
        ];
    }
}
