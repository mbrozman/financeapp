<?php

namespace App\Filament\Widgets;

use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;

class ReserveFundWidget extends Widget
{
    protected static string $view = 'filament.widgets.reserve-fund-widget';
    protected int | string | array $columnSpan = 'full';

    public function getReserveData(): ?array
    {
        $userId = Auth::id();
        $plan = FinancialPlan::where('user_id', $userId)->where('is_active', true)->first();

        if (!$plan) return null;

        /** @var FinancialPlanItem|null $reserveItem */
        $reserveItem = FinancialPlanItem::where('financial_plan_id', $plan->id)
            ->where('is_reserve', true)
            ->first();

        if (!$reserveItem) return null;

        $planIncome = (float) $plan->monthly_income;
        if (!$planIncome || !$reserveItem) return null;

        $monthlyAllocation = $planIncome * ($reserveItem->percentage / 100);
        $targetAmount = (float) $plan->reserve_target;

        // Cumulative amount saved — sum of all expense transactions in reserve categories (all time)
        // This is kept for reporting purposes but NOT added to account balances anymore
        $reserveTransactionsSum = Transaction::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('type', 'expense')
                  ->orWhere(fn($q2) => $q2->where('type', 'transfer')->where('amount', '<', 0));
            })
            ->whereHas('category', function ($q) use ($reserveItem) {
                $q->where('financial_plan_item_id', $reserveItem->id)
                  ->orWhereHas('parent', fn($q2) => $q2->where('financial_plan_item_id', $reserveItem->id));
            })
            ->sum('amount');
        $reserveTransactionsSum = abs((float) $reserveTransactionsSum);

        // Ground truth for "Saved" = balances of all accounts of type 'reserve'
        $reserveAccountBalance = \App\Models\Account::where('user_id', $userId)
            ->where('type', 'reserve')
            ->where('is_active', true)
            ->sum('balance');
        $reserveAccountBalance = abs((float) $reserveAccountBalance);

        // We only use the account balances for the total saved amount
        $savedAmount = $reserveAccountBalance;

        // Current month contribution (reserve transactions only this month)
        $thisMonthSaved = Transaction::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('type', 'expense')
                  ->orWhere(fn($q2) => $q2->where('type', 'transfer')->where('amount', '<', 0));
            })
            ->whereYear('transaction_date', now()->year)
            ->whereMonth('transaction_date', now()->month)
            ->whereHas('category', function ($q) use ($reserveItem) {
                $q->where('financial_plan_item_id', $reserveItem->id)
                  ->orWhereHas('parent', fn($q2) => $q2->where('financial_plan_item_id', $reserveItem->id));
            })
            ->sum('amount');
        $thisMonthSaved = abs((float) $thisMonthSaved);

        $progress = $targetAmount > 0 ? min(100, round(($savedAmount / $targetAmount) * 100, 1)) : 0;
        $monthsCoverage = $monthlyAllocation > 0 ? round($savedAmount / $monthlyAllocation, 1) : 0;
        
        // Months remaining = (Total Target - Current Saved) / Monthly Allocation
        $monthsRemaining = $monthlyAllocation > 0 ? max(0, ceil(($targetAmount - $savedAmount) / $monthlyAllocation)) : 0;

        // Find pillar index (1-based)
        $pillarIndex = $plan->items()->orderBy('id')->get()->search(fn($item) => $item->id === $reserveItem->id) + 1;

        return [
            'name'             => $reserveItem->name,
            'index'            => $pillarIndex,
            'percentage'       => $reserveItem->percentage,
            'monthly_target'   => $monthlyAllocation,
            'target'           => $targetAmount,
            'saved'            => $savedAmount,
            'reserve_tx'       => $reserveTransactionsSum,
            'cash_balance'     => $reserveAccountBalance,
            'remaining'        => max(0, $targetAmount - $savedAmount),
            'progress'         => $progress,
            'months_coverage'  => $monthsCoverage,
            'months_remaining' => $monthsRemaining,
            'this_month'       => $thisMonthSaved,
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'reserve' => $this->getReserveData(),
        ]);
    }
}
