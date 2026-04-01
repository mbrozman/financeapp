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

        $totalBank = BigDecimal::zero();
        $totalCash = BigDecimal::zero();
        $totalReserve = BigDecimal::zero();

        foreach ($accounts as $account) {
            $eurValueString = CurrencyService::convertToEur((string) $account->balance, $account->currency_id);
            $eurValue = BigDecimal::of($eurValueString);
            
            if ($account->type === 'bank') $totalBank = $totalBank->plus($eurValue);
            elseif ($account->type === 'cash') $totalCash = $totalCash->plus($eurValue);
            elseif ($account->type === 'reserve') $totalReserve = $totalReserve->plus($eurValue);
        }
        
        $totalLiquidity = $totalBank->plus($totalCash)->plus($totalReserve);

        return [
            'total_liquidity' => round($totalLiquidity->toFloat(), 2),
            'total_bank' => round($totalBank->toFloat(), 2),
            'total_cash' => round($totalCash->toFloat(), 2),
            'total_reserve' => round($totalReserve->toFloat(), 2),
        ];
    }

    public function getYearlyCashflow($userId, int $year): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "dashboard_yearly_cashflow_{$userId}_{$year}",
            900, // 15 minút caching
            function () use ($userId, $year) {
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
            
            $incomeBD = $monthData && $monthData->total_income !== null ? BigDecimal::of((string) $monthData->total_income)->abs() : BigDecimal::zero();
            $expenseBD = $monthData && $monthData->total_expense !== null ? BigDecimal::of((string) $monthData->total_expense)->abs() : BigDecimal::zero();
            
            $incomeValues[] = round($incomeBD->toFloat(), 2);
            $expenseValues[] = round($expenseBD->toFloat(), 2);
            $surplusValues[] = round($incomeBD->minus($expenseBD)->toFloat(), 2);
        }

        $totalIncomeBD = isset($totals->total_income) && $totals->total_income !== null ? BigDecimal::of((string) $totals->total_income)->abs() : BigDecimal::zero();
        $totalExpenseBD = isset($totals->total_expense) && $totals->total_expense !== null ? BigDecimal::of((string) $totals->total_expense)->abs() : BigDecimal::zero();

                return [
                    'total_income' => round($totalIncomeBD->toFloat(), 2),
                    'total_expense' => round($totalExpenseBD->toFloat(), 2),
                    'total_surplus' => round($totalIncomeBD->minus($totalExpenseBD)->toFloat(), 2),
                    'labels' => $labels,
                    'income_values' => $incomeValues,
                    'expense_values' => $expenseValues,
                    'surplus_values' => $surplusValues,
                ];
            }
        );
    }

    public function getRealityVsPlanSeries($userId, string $filter = 'with_roi'): array
    {
        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();
        $year = now()->year;
        
        $labels = [];
        $realityValues = [];
        $modelValues = [];
        $savedValues = [];

        for ($i = 1; $i <= 12; $i++) {
            $date = Carbon::create($year, $i, 1);
            $labels[] = $date->translatedFormat('M Y');
            $realityValues[$i] = 0;
            $modelValues[$i] = 0;
            $savedValues[$i] = 0;
        }

        if (!$plan) {
            return [
                'labels' => $labels,
                'reality_values' => array_values($realityValues),
                'model_values' => array_values($modelValues),
                'monthly_saved_values' => array_values($savedValues),
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

        // 2. Get monthly invested amount (Buys)
        $investments = \App\Models\InvestmentTransaction::where('user_id', $userId)
            ->where('type', \App\Enums\TransactionType::BUY)
            ->whereYear('transaction_date', $year)
            ->get();
            
        $monthlySavedBD = [];
        for ($i = 1; $i <= 12; $i++) { $monthlySavedBD[$i] = BigDecimal::zero(); }

        foreach ($investments as $tx) {
            $month = $tx->transaction_date->month;
            $amountBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
            if ($tx->commission) { $amountBase = $amountBase->plus($tx->commission); }
            $amountEur = CurrencyService::convertToEur((string) $amountBase, $tx->currency_id, (float)$tx->exchange_rate);
            $monthlySavedBD[$month] = $monthlySavedBD[$month]->plus($amountEur);
        }

        // 3. Get monthly reserve deposits (Transactions)
        $reserveItem = $plan->items->where('is_reserve', true)->first();
        if ($reserveItem) {
            $reserveTxs = Transaction::where('user_id', $userId)
                ->whereYear('transaction_date', $year)
                ->where(function ($q) {
                    $q->where('type', 'expense')
                      ->orWhere(fn($q2) => $q2->where('type', 'transfer')->where('amount', '<', 0));
                })
                ->whereHas('category', function ($q) use ($reserveItem) {
                    $q->where('financial_plan_item_id', $reserveItem->id)
                      ->orWhereHas('parent', fn($q2) => $q2->where('financial_plan_item_id', $reserveItem->id));
                })
                ->get();
                
            foreach ($reserveTxs as $tx) {
                $month = $tx->transaction_date->month;
                $monthlySavedBD[$month] = $monthlySavedBD[$month]->plus(abs((float)$tx->amount));
            }
        }

        $monthlySavingsIdeal = $plan->getMonthlySavingsAmount();
        
        $accumulatedReality = BigDecimal::zero();
        $accumulatedModel = BigDecimal::zero();

        for ($i = 1; $i <= 12; $i++) {
            if ($i < $startMonth) {
                $realityValues[$i] = null;
                $modelValues[$i] = null;
                $savedValues[$i] = 0;
                continue;
            }

            // Reality: Income + Expense (expense is negative)
            $data = $monthlyData->get($i);
            $incomeBD = $data && $data->income !== null ? BigDecimal::of((string)$data->income)->abs() : BigDecimal::zero();
            $expenseBD = $data && $data->expense !== null ? BigDecimal::of((string)$data->expense)->abs() : BigDecimal::zero();
            $surplusBD = $incomeBD->minus($expenseBD);
            
            $accumulatedReality = $accumulatedReality->plus($surplusBD);
            $accumulatedModel = $accumulatedModel->plus($monthlySavingsIdeal);

            $realityValues[$i] = round($accumulatedReality->toFloat(), 2);
            $modelValues[$i] = round($accumulatedModel->toFloat(), 2);
            $savedValues[$i] = round($monthlySavedBD[$i]->toFloat(), 2);
        }

        // Calculate is_ahead based on the LATEST available month
        $lastMonth = now()->month;
        $isAhead = ($realityValues[$lastMonth] ?? 0) >= ($modelValues[$lastMonth] ?? 0);

        return [
            'labels' => $labels,
            'reality_values' => array_values($realityValues),
            'model_values' => array_values($modelValues),
            'monthly_saved_values' => array_values($savedValues),
            'is_ahead' => $isAhead,
        ];
    }
}
