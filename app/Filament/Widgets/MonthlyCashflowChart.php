<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyCashflowChart extends ChartWidget
{
    protected static ?string $heading = 'Mesačný Cashflow: Príjmy vs. Výdavky (€)';
    protected static ?int $sort = 2;

    public ?string $filter = '6'; // Predvolených 6 mesiacov

    protected function getFilters(): ?array
    {
        return [
            '3' => 'Posledné 3 mesiace',
            '6' => 'Posledných 6 mesiacov',
            '12' => 'Posledný rok',
        ];
    }

    protected function getData(): array
    {
        $months = (int) ($this->filter ?? 6);
        $userId = auth()->id();

        // 1. Získame dáta za zvolené obdobie
        $data = Transaction::select(
            DB::raw("date_trunc('month', transaction_date) as month"),
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
        )
            ->where('user_id', $userId)
            ->where('transaction_date', '>=', now()->subMonths($months)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 2. Pripravíme polia pre graf
        $labels = $data->map(fn($item) => Carbon::parse($item->month)->format('M Y'))->toArray();
        $incomeValues = $data->pluck('total_income')->map(fn($v) => abs((float)$v))->toArray();
        $expenseValues = $data->pluck('total_expense')->map(fn($v) => abs((float)$v))->toArray();
        
        // Výpočet čistého toku (Profit/Loss)
        $netFlowValues = [];
        foreach ($data as $index => $row) {
            $netFlowValues[] = (float)$row->total_income - (float)$row->total_expense;
        }

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
        return false;
    }
}
