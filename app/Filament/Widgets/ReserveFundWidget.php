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
        
        // 1. Hľadáme cieľ označený ako "Finančná rezerva"
        $reserveGoal = \App\Models\Goal::where('user_id', $userId)
            ->where('is_reserve', true)
            ->first();

        if (!$reserveGoal) return null;

        // 2. Nájdeme položku v AKTÍVNOM pláne, ktorá tento cieľ plní
        $plan = \App\Models\FinancialPlan::where('user_id', $userId)->where('is_active', true)->first();
        $monthlyAllocation = 0;
        $percentage = 0;

        if ($plan) {
            $reserveItem = \App\Models\FinancialPlanItem::where('financial_plan_id', $plan->id)
                ->where('goal_id', $reserveGoal->id)
                ->first();

            if ($reserveItem) {
                $monthlyAllocation = (float) ($plan->monthly_income * ($reserveItem->percentage / 100));
                $percentage = (float) $reserveItem->percentage;
            }
        }

        $targetAmount = (float) $reserveGoal->target_amount;
        $savedAmount = (float) $reserveGoal->current_amount;
        $name = $reserveGoal->name;

        if ($targetAmount <= 0 && $savedAmount <= 0) return null;

        $progress = $targetAmount > 0 ? round(($savedAmount / $targetAmount) * 100, 1) : 0;
        $monthsCoverage = $monthlyAllocation > 0 ? round($savedAmount / $monthlyAllocation, 1) : 0;
        $remaining = max(0, $targetAmount - $savedAmount);
        $monthsRemaining = $monthlyAllocation > 0 ? ceil($remaining / $monthlyAllocation) : 0;

        return [
            'name'             => $name,
            'target'           => $targetAmount,
            'saved'            => $savedAmount,
            'remaining'        => $remaining,
            'progress'         => $progress,
            'months_coverage'  => $monthsCoverage,
            'months_remaining' => $monthsRemaining,
            'index'            => $reserveGoal->id,
            'percentage'       => $percentage,
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'reserve' => $this->getReserveData(),
        ]);
    }
}
