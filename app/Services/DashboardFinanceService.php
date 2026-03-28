<?php

namespace App\Services;

use App\Models\Account;
use App\Models\FinancialPlan;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardFinanceService
{
    public function getLiquidityStats($userId): array
    {
        $accounts = Account::with('currency')
            ->where('user_id', $userId)
            ->whereIn('type', ['bank', 'cash', 'reserve'])
            ->get();

        $totalBank = $accounts->where('type', 'bank')->sum(fn ($account) => (float) CurrencyService::convertToEur((string) $account->balance, $account->currency_id));
        $totalCash = $accounts->where('type', 'cash')->sum(fn ($account) => (float) CurrencyService::convertToEur((string) $account->balance, $account->currency_id));
        $totalReserve = $accounts->where('type', 'reserve')->sum(fn ($account) => (float) CurrencyService::convertToEur((string) $account->balance, $account->currency_id));
        
        $totalLiquidity = $totalBank + $totalCash + $totalReserve;

        return [
            'total_liquidity' => $totalLiquidity,
            'total_bank' => $totalBank,
            'total_cash' => $totalCash,
            'total_reserve' => $totalReserve,
        ];
    }

    public function getYearlyCashflow($userId, int $year): array
    {
        $totals = Transaction::select(
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->first();

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

        $labels = [];
        $incomeValues = [];
        $expenseValues = [];
        $surplusValues = [];

        for ($i = 1; $i <= 12; $i++) {
            $monthDate = Carbon::create($year, $i, 1);
            $labels[] = $monthDate->translatedFormat('M');
            $monthData = $data->first(fn ($item) => Carbon::parse($item->month)->month === $i);
            
            $income = $monthData ? abs((float) $monthData->total_income) : 0.0;
            $expense = $monthData ? abs((float) $monthData->total_expense) : 0.0;
            
            $incomeValues[] = $income;
            $expenseValues[] = $expense;
            $surplusValues[] = round($income - $expense, 2);
        }

        $totalIncome = abs((float) ($totals->total_income ?? 0));
        $totalExpense = abs((float) ($totals->total_expense ?? 0));

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'total_surplus' => round($totalIncome - $totalExpense, 2),
            'labels' => $labels,
            'income_values' => $incomeValues,
            'expense_values' => $expenseValues,
            'surplus_values' => $surplusValues,
        ];
    }

    public function getRealityVsPlanSeries($userId, string $filter = 'with_roi'): array
    {
        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();
        $year = now()->year;
        
        $labels = [];
        $realityValues = [];
        $modelValues = [];

        for ($i = 1; $i <= 12; $i++) {
            $date = Carbon::create($year, $i, 1);
            $labels[] = $date->translatedFormat('M Y');
            $realityValues[$i] = 0;
            $modelValues[$i] = 0;
        }

        if (!$plan) {
            return [
                'labels' => $labels,
                'reality_values' => array_values($realityValues),
                'model_values' => array_values($modelValues),
                'is_ahead' => true,
            ];
        }

        $startMonth = $plan->created_at->month;
        
        // 1. Get real monthly surplus (Income - Expense)
        $monthlyData = Transaction::select(
            DB::raw("EXTRACT(MONTH FROM transaction_date) as month"),
            DB::raw("SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income"),
            DB::raw("SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense")
        )
            ->where('user_id', $userId)
            ->whereYear('transaction_date', $year)
            ->groupBy('month')
            ->get()
            ->keyBy('month');

        $monthlySavingsIdeal = $plan->getMonthlySavingsAmount();
        
        $accumulatedReality = BigDecimal::zero();
        $accumulatedModel = BigDecimal::zero();

        for ($i = 1; $i <= 12; $i++) {
            if ($i < $startMonth) {
                $realityValues[$i] = null;
                $modelValues[$i] = null;
                continue;
            }

            // Reality: Income - Expense
            $data = $monthlyData->get($i);
            $income = $data ? (float)$data->income : 0;
            $expense = $data ? (float)$data->expense : 0;
            $surplus = $income + $expense; // Expense is already negative
            
            $accumulatedReality = $accumulatedReality->plus($surplus);
            $accumulatedModel = $accumulatedModel->plus($monthlySavingsIdeal);

            $realityValues[$i] = round($accumulatedReality->toFloat(), 2);
            $modelValues[$i] = round($accumulatedModel->toFloat(), 2);
        }

        // Calculate is_ahead based on the LATEST available month
        $lastMonth = now()->month;
        $isAhead = ($realityValues[$lastMonth] ?? 0) >= ($modelValues[$lastMonth] ?? 0);

        return [
            'labels' => $labels,
            'reality_values' => array_values($realityValues),
            'model_values' => array_values($modelValues),
            'is_ahead' => $isAhead,
        ];
    }
}
