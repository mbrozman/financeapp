<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyCashflowChart extends ChartWidget
{
    public function getHeading(): ?string
    {
        $year = now()->year;
        $userId = auth()->id();
        
        $totals = Transaction::select(
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->first();

        $income = number_format(abs($totals->total_income), 0, ',', ' ');
        $expense = number_format(abs($totals->total_expense), 0, ',', ' ');

        return "Cashflow {$year}: Príjmy {$income} € vs. Výdavky {$expense} €";
    }

    protected function getData(): array
    {
        $userId = auth()->id();
        $year = now()->year;

        // Získame dáta za celý aktuálny rok
        $data = Transaction::select(
            DB::raw("date_trunc('month', transaction_date) as month"),
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Inicializujeme polia pre všetkých 12 mesiacov
        $labels = [];
        $incomeValues = [];
        $expenseValues = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthDate = Carbon::create($year, $i, 1);
            $labels[] = $monthDate->translatedFormat('M');
            
            $monthData = $data->first(fn($item) => Carbon::parse($item->month)->month === $i);
            
            $incomeValues[] = $monthData ? abs((float)$monthData->total_income) : 0;
            $expenseValues[] = $monthData ? abs((float)$monthData->total_expense) : 0;
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
