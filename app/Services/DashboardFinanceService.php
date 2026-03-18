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
    public function getLiquidityStats(int $userId): array
    {
        $bankAccounts = Account::with('currency')
            ->where('user_id', $userId)
            ->where('type', 'bank')
            ->get();

        $cashAccounts = Account::with('currency')
            ->where('user_id', $userId)
            ->where('type', 'cash')
            ->get();

        $totalBank = $bankAccounts->sum(fn ($account) => (float) CurrencyService::convertToEur((string) $account->balance, $account->currency_id));
        $totalCash = $cashAccounts->sum(fn ($account) => (float) CurrencyService::convertToEur((string) $account->balance, $account->currency_id));
        $totalLiquidity = $totalBank + $totalCash;

        return [
            'total_liquidity' => $totalLiquidity,
            'total_bank' => $totalBank,
            'total_cash' => $totalCash,
        ];
    }

    public function getYearlyCashflow(int $userId, int $year): array
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

        for ($i = 1; $i <= 12; $i++) {
            $monthDate = Carbon::create($year, $i, 1);
            $labels[] = $monthDate->translatedFormat('M');
            $monthData = $data->first(fn ($item) => Carbon::parse($item->month)->month === $i);
            $incomeValues[] = $monthData ? abs((float) $monthData->total_income) : 0.0;
            $expenseValues[] = $monthData ? abs((float) $monthData->total_expense) : 0.0;
        }

        return [
            'total_income' => abs((float) ($totals->total_income ?? 0)),
            'total_expense' => abs((float) ($totals->total_expense ?? 0)),
            'labels' => $labels,
            'income_values' => $incomeValues,
            'expense_values' => $expenseValues,
        ];
    }

    public function getRealityVsPlanSeries(int $userId, string $filter = 'with_roi'): array
    {
        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();

        if (!$plan) {
            return [
                'labels' => [],
                'reality_values' => [],
                'model_values' => [],
                'is_ahead' => true,
            ];
        }

        $snapshots = PortfolioSnapshot::where('user_id', $userId)
            ->whereIn('id', function ($query) use ($userId) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('net_worth_snapshots')
                    ->where('user_id', $userId)
                    ->groupBy(DB::raw("date_trunc('month', recorded_at)"));
            })
            ->orderBy('recorded_at', 'asc')
            ->get();

        $labels = [];
        $realityValues = [];
        $modelValues = [];

        $firstSnapshot = $snapshots->first();
        $currentModelValue = $firstSnapshot
            ? BigDecimal::of($firstSnapshot->total_market_value_eur)
            : BigDecimal::zero();

        $monthlySavingsIdeal = $plan->getMonthlySavingsAmount();
        $monthlyInterestRate = ($filter === 'with_roi') ? (8 / 100) / 12 : 0;

        if ($snapshots->isEmpty()) {
            $date = now()->startOfYear();
            for ($i = 0; $i < 12; $i++) {
                $labels[] = $date->format('M Y');
                $realityValues[] = null;
                $currentModelValue = $currentModelValue->plus($monthlySavingsIdeal);
                if ($monthlyInterestRate > 0) {
                    $interest = $currentModelValue->multipliedBy($monthlyInterestRate);
                    $currentModelValue = $currentModelValue->plus($interest);
                }
                $modelValues[] = round($currentModelValue->toFloat(), 2);
                $date->addMonth();
            }
        } else {
            foreach ($snapshots as $snapshot) {
                $labels[] = $snapshot->recorded_at->format('M Y');
                $realityValues[] = (float) $snapshot->total_market_value_eur;

                if ($snapshot->id === $firstSnapshot->id) {
                    $modelValues[] = $currentModelValue->toFloat();
                    continue;
                }

                $currentModelValue = $currentModelValue->plus($monthlySavingsIdeal);
                if ($monthlyInterestRate > 0) {
                    $interest = $currentModelValue->multipliedBy($monthlyInterestRate);
                    $currentModelValue = $currentModelValue->plus($interest);
                }
                $modelValues[] = round($currentModelValue->toFloat(), 2);
            }
        }

        $lastReality = end($realityValues);
        $lastModel = end($modelValues);
        $isAhead = ($lastReality !== null) ? $lastReality >= $lastModel : true;

        return [
            'labels' => $labels,
            'reality_values' => $realityValues,
            'model_values' => $modelValues,
            'is_ahead' => $isAhead,
        ];
    }
}
