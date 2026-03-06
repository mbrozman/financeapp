<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyCashflowChart extends ChartWidget
{
    protected static ?string $heading = 'Príjmy vs Výdavky (posledných 6 mesiacov)';
    protected static ?int $sort = 2; // Zobrazí sa pod kartami (Stats mali default 1)

    protected function getData(): array
    {
        // 1. Získame dáta za posledných 6 mesiacov
        $data = Transaction::select(
            DB::raw("date_trunc('month', transaction_date) as month"), // PostgreSQL funkcia na orezanie dátumu na mesiac
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN ABS(amount) ELSE 0 END) as total_expense")
        )
            ->where('transaction_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // 2. Pripravíme polia pre graf
        $labels = $data->map(fn($item) => Carbon::parse($item->month)->format('M Y'))->toArray();
        $incomeValues = $data->pluck('total_income')->toArray();
        $expenseValues = $data->pluck('total_expense')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Príjmy',
                    'data' => $incomeValues,
                    'backgroundColor' => '#22c55e', // Zelená (success)
                    'borderColor' => '#22c55e',
                ],
                [
                    'label' => 'Výdavky',
                    'data' => $expenseValues,
                    'backgroundColor' => '#ef4444', // Červená (danger)
                    'borderColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar'; // Môžeš zmeniť na 'line' pre čiarový graf
    }
    public static function canView(): bool
    {
        return false; // Toto skryje widget z Dashboardu
    }
}
