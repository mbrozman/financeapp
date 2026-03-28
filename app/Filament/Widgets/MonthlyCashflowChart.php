<?php

namespace App\Filament\Widgets;

use App\Services\DashboardFinanceService;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class MonthlyCashflowChart extends ChartWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];


    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        $year = now()->year;
        $userId = auth()->id();
        $cashflow = app(DashboardFinanceService::class)->getYearlyCashflow($userId, $year);
        $income = number_format($cashflow['total_income'], 0, ',', ' ');
        $expense = number_format($cashflow['total_expense'], 0, ',', ' ');
        $surplus = number_format($cashflow['total_surplus'], 0, ',', ' ');

        return "Cashflow {$year}: Príjmy {$income} € vs. Výdavky {$expense} € (Úspora {$surplus} €)";
    }

    protected function getData(): array
    {
        $userId = auth()->id();
        $year = now()->year;
        $cashflow = app(DashboardFinanceService::class)->getYearlyCashflow($userId, $year);

        return [
            'datasets' => [
                [
                    'label' => 'Príjmy',
                    'data' => $cashflow['income_values'],
                    'backgroundColor' => '#228b22',
                    'borderColor' => '#228b22',
                ],
                [
                    'label' => 'Výdavky',
                    'data' => $cashflow['expense_values'],
                    'backgroundColor' => '#ff0000',
                    'borderColor' => '#ff0000',
                ],
                [
                    'label' => 'Odložené / Úspora',
                    'data' => $cashflow['surplus_values'],
                    'backgroundColor' => '#87ceeb',
                    'borderColor' => '#87ceeb',
                ],
            ],
            'labels' => $cashflow['labels'],
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
                'x' => ['stacked' => false],
                'y' => ['stacked' => false],
            ],
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
