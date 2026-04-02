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

        // Ak nemáme cieľ, skúsime starú logiku cez FinancialPlan (kompatibilita)
        if (!$reserveGoal) {
            $plan = \App\Models\FinancialPlan::where('user_id', $userId)->where('is_active', true)->first();
            if (!$plan) return null;

            $reserveItem = \App\Models\FinancialPlanItem::where('financial_plan_id', $plan->id)
                ->where('is_reserve', true)
                ->first();

            if (!$reserveItem) return null;

            $targetAmount = (float) $plan->reserve_target;
            
            // Stará logika: len účty typu reserve
            $savedAmount = (float) \App\Models\Account::where('user_id', $userId)
                ->where('type', 'reserve')
                ->where('is_active', true)
                ->sum('balance');
            
            $name = $reserveItem->name;
            $monthlyAllocation = (float) ($plan->monthly_income * ($reserveItem->percentage / 100));
        } else {
            // NOVÁ LOGIKA: Dáta priamo z Goal modelu (sčíta účty aj investície!)
            $targetAmount = (float) $reserveGoal->target_amount;
            $savedAmount = (float) $reserveGoal->current_amount;
            $name = $reserveGoal->name;
            
            // Pre výpočet mesačnej alokácie si požičiame info z aktívneho plánu, ak existuje
            $plan = \App\Models\FinancialPlan::where('user_id', $userId)->where('is_active', true)->first();
            $monthlyAllocation = 0;
            if ($plan) {
                $reserveItem = \App\Models\FinancialPlanItem::where('financial_plan_id', $plan->id)
                    ->where('is_reserve', true)
                    ->first();
                if ($reserveItem) {
                    $monthlyAllocation = (float) ($plan->monthly_income * ($reserveItem->percentage / 100));
                }
            }
        }

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
            'index'            => $reserveGoal->id ?? 1,
            'percentage'       => $reserveItem->percentage ?? 0,
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'reserve' => $this->getReserveData(),
        ]);
    }
}
