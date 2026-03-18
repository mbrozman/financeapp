<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialPlan;
use App\Models\InvestmentTransaction;
use App\Models\Transaction;
use Carbon\Carbon;

class MonthlyBudgetService
{
    public function getBudgetData(string $selectedMonth, int $userId): array
    {
        $date = Carbon::parse($selectedMonth . '-01');

        $actualIncome = abs((float) Transaction::where('user_id', $userId)
            ->where('type', TransactionType::INCOME)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount'));

        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();
        $plannedIncome = $plan ? (float) $plan->monthly_income : 2200;
        $incomeDiff = $actualIncome - $plannedIncome;

        $totalSpentAbs = abs((float) Transaction::where('user_id', $userId)
            ->where('type', TransactionType::EXPENSE)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount'));

        $totalInvested = (float) InvestmentTransaction::where('user_id', $userId)
            ->where('type', TransactionType::BUY)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->get()
            ->sum(function ($tx) {
                $amountBase = (float) $tx->quantity * (float) $tx->price_per_unit;
                return (float) CurrencyService::convertToEur((string) $amountBase, $tx->currency_id, (float) $tx->exchange_rate);
            });

        $pillarData = [];
        if ($plan) {
            foreach ($plan->items as $item) {
                $pLimit = ($actualIncome * (float) $item->percentage) / 100;
                $categoryBudgets = $this->getCategoryBudgets((int) $item->id, $date, $selectedMonth, $userId);
                $pActualAbs = collect($categoryBudgets)->flatten(1)->sum('actual');

                $pillarData[] = [
                    'name' => $item->name,
                    'limit' => $pLimit,
                    'actual' => $pActualAbs,
                    'percent' => $pLimit > 0 ? ($pActualAbs / $pLimit) * 100 : 0,
                    'budgets' => $categoryBudgets,
                ];
            }
        }

        $now = now();
        $daysRemaining = ($now->format('Y-m') === $selectedMonth)
            ? $now->diffInDays($now->copy()->endOfMonth()) + 1
            : 0;

        return [
            'actual_income' => $actualIncome,
            'planned_income' => $plannedIncome,
            'income_diff' => $incomeDiff,
            'total_spent' => $totalSpentAbs,
            'total_invested' => $totalInvested,
            'savings' => $actualIncome - ($totalSpentAbs + $totalInvested),
            'pillars' => $pillarData,
            'days_remaining' => $daysRemaining,
        ];
    }

    private function getCategoryBudgets(int $pillarId, Carbon $date, string $selectedMonth, int $userId): array
    {
        $categories = Category::with('parent')
            ->where('user_id', $userId)
            ->where('financial_plan_item_id', $pillarId)
            ->whereNotNull('monthly_limit')
            ->get();

        $groupedRes = [];

        foreach ($categories as $cat) {
            if (!$cat->parent_id) {
                continue;
            }

            $actAbs = abs($cat->actualAmount($selectedMonth));
            $lim = (float) $cat->monthly_limit;
            $parentCategoryName = $cat->parent->name;

            $itemData = [
                'category' => $cat->name,
                'actual' => $actAbs,
                'limit' => $lim,
                'percent' => $lim > 0 ? ($actAbs / $lim) * 100 : 0,
            ];

            if (!isset($groupedRes[$parentCategoryName])) {
                $groupedRes[$parentCategoryName] = [];
            }

            $groupedRes[$parentCategoryName][] = $itemData;
        }

        return $groupedRes;
    }
}
