<?php

namespace App\Filament\Widgets;

use App\Services\DashboardFinanceService;
use Filament\Widgets\ChartWidget;

class MonthlyCashflowChart extends ChartWidget
{
    public function getHeading(): ?string
    {
        $year = now()->year;
        $userId = auth()->id();
        $cashflow = app(DashboardFinanceService::class)->getYearlyCashflow($userId, $year);
        $income = number_format($cashflow['total_income'], 0, ',', ' ');
        $expense = number_format($cashflow['total_expense'], 0, ',', ' ');

        return "Cashflow {$year}: Príjmy {$income} € vs. Výdavky {$expense} €";
    }

    protected function getData(): array
    {
        $userId = auth()->id();
        $year = now()->year;
        $cashflow = app(DashboardFinanceService::class)->getYearlyCashflow($userId, $year);
        $labels = $cashflow['labels'];
        $incomeValues = $cashflow['income_values'];
        $expenseValues = $cashflow['expense_values'];

        return [
            'datasets' => [
                [
                    'label' => 'Príjmy',
                    'data' => $incomeValues,
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#22c55e',
                ],
                [
                    'label' => 'Výdavky',
                    'data' => $expenseValues,
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => [
                    'stacked' => false,
                ],
                'y' => [
                    'stacked' => false,
                ],
            ],
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
