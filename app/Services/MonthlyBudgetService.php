<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Category;
use App\Models\FinancialPlan;
use App\Models\InvestmentTransaction;
use App\Models\Transaction;
use Carbon\Carbon;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class MonthlyBudgetService
{
    public function getBudgetData(string $selectedMonth, $userId): array
    {
        $date = Carbon::parse($selectedMonth . '-01');

        $actualIncome = BigDecimal::of(Transaction::where('user_id', $userId)
            ->where('type', TransactionType::INCOME)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount'))->abs();

        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();
        $plannedIncome = BigDecimal::of($plan ? $plan->monthly_income : 2200);
        $incomeDiff = $actualIncome->minus($plannedIncome);

        $totalSpentAbs = BigDecimal::of(Transaction::where('user_id', $userId)
            ->where('type', TransactionType::EXPENSE)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount'))->abs();

        $totalInvested = BigDecimal::zero();
        $investments = InvestmentTransaction::where('user_id', $userId)
            ->where('type', TransactionType::BUY)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->get();

        foreach ($investments as $tx) {
            $amountBase = BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit);
            if ($tx->commission) {
                $amountBase = $amountBase->plus($tx->commission);
            }
            $amountEur = CurrencyService::convertToEur((string) $amountBase, $tx->currency_id, $tx->exchange_rate);
            $totalInvested = $totalInvested->plus($amountEur);
        }

        $pillarData = [];
        if ($plan) {
            foreach ($plan->items as $item) {
                $pLimit = $actualIncome->multipliedBy($item->percentage)->dividedBy(100, 4, RoundingMode::HALF_UP);
                $categoryBudgets = $this->getCategoryBudgets($item->id, $date, $selectedMonth, $userId);
                
                $pActualAbs = BigDecimal::zero();
                foreach($categoryBudgets as $budgetList) {
                    foreach($budgetList as $budget) {
                        $pActualAbs = $pActualAbs->plus($budget['actual']);
                    }
                }

                $pillarData[] = [
                    'name' => $item->name,
                    'limit' => (float) (string) $pLimit,
                    'actual' => (float) (string) $pActualAbs,
                    'percent' => $pLimit->isGreaterThan(0) 
                        ? (float) (string) $pActualAbs->dividedBy($pLimit, 4, RoundingMode::HALF_UP)->multipliedBy(100) 
                        : 0,
                    'budgets' => $categoryBudgets,
                ];
            }
        }

        $now = now();
        $daysRemaining = ($now->format('Y-m') === $selectedMonth)
            ? $now->diffInDays($now->copy()->endOfMonth()) + 1
            : 0;

        return [
            'actual_income' => (float) (string) $actualIncome,
            'planned_income' => (float) (string) $plannedIncome,
            'income_diff' => (float) (string) $incomeDiff,
            'total_spent' => (float) (string) $totalSpentAbs,
            'total_invested' => (float) (string) $totalInvested,
            'savings' => (float) (string) $actualIncome->minus($totalSpentAbs->plus($totalInvested)),
            'pillars' => $pillarData,
            'days_remaining' => $daysRemaining,
        ];
    }

    private function getCategoryBudgets($pillarId, Carbon $date, string $selectedMonth, $userId): array
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

            $actAbs = BigDecimal::of($cat->actualAmount($selectedMonth))->abs();
            $lim = BigDecimal::of($cat->monthly_limit ?? 0);
            $parentCategoryName = $cat->parent->name;

            $itemData = [
                'category' => $cat->name,
                'actual' => (float) (string) $actAbs,
                'limit' => (float) (string) $lim,
                'percent' => $lim->isGreaterThan(0) 
                    ? (float) (string) $actAbs->dividedBy($lim, 4, RoundingMode::HALF_UP)->multipliedBy(100) 
                    : 0,
            ];

            if (!isset($groupedRes[$parentCategoryName])) {
                $groupedRes[$parentCategoryName] = [];
            }

            $groupedRes[$parentCategoryName][] = $itemData;
        }

        return $groupedRes;
    }
}
