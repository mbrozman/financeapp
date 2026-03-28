<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;
use Carbon\Carbon;

class PillarPerformanceWidget extends Widget
{
    protected static string $view = 'filament.widgets.pillar-performance-widget';

    public string $period;

    protected static ?int $sort = 0; // Top

    protected int | string | array $columnSpan = 'full';

    public function mount(): void
    {
        $this->period = now()->format('Y-m');
    }

    public function getPillarData(): array
    {
        $userId = Auth::id();
        $plan = FinancialPlan::where('user_id', $userId)->where('is_active', true)->first();
        
        if (!$plan) {
            return [];
        }

        $planIncome = (float) $plan->monthly_income;
        $items = FinancialPlanItem::where('financial_plan_id', $plan->id)->orderBy('id')->get();
        
        $carbon = Carbon::parse($this->period . '-01');

        // Calculate actual total income for this period
        $actualMonthIncome = Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereYear('transaction_date', $carbon->year)
            ->whereMonth('transaction_date', $carbon->month)
            ->sum('amount');
        $actualMonthIncome = (float) $actualMonthIncome;

        $extraIncome = max(0, $actualMonthIncome - $planIncome);
        $pillarSavings = 0;

        $pillars = [];
        foreach ($items as $item) {
            $allocation = $planIncome * ($item->percentage / 100);
            
            // Get spent amount for this pillar (including subcategories)
            $spent = Transaction::where('user_id', $userId)
                ->where(function ($q) {
                    $q->where('type', 'expense')
                      ->orWhere(fn($q2) => $q2->where('type', 'transfer')->where('amount', '<', 0));
                })
                ->whereYear('transaction_date', $carbon->year)
                ->whereMonth('transaction_date', $carbon->month)
                ->whereHas('category', function ($q) use ($item) {
                    $q->where('financial_plan_item_id', $item->id)
                      ->orWhereHas('parent', fn($q2) => $q2->where('financial_plan_item_id', $item->id));
                })
                ->sum('amount');
            $spent = abs((float) $spent);

            $savings = max(0, $allocation - $spent);
            $pillarSavings += $savings;

            $pillars[] = [
                'name' => $item->name,
                'percentage' => $item->percentage,
                'allocated_limit' => $allocation,
                'actual_spent' => $spent,
                'is_saving' => (bool) $item->is_saving,
                'color' => $item->color ?? '#94a3b8',
            ];
        }

        return [
            'pillars' => $pillars,
            'surplus' => [
                'total' => $extraIncome + $pillarSavings,
                'extra_income' => $extraIncome,
                'pillar_savings' => $pillarSavings,
            ],
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.widgets.pillar-performance-widget', [
            'data' => $this->getPillarData(),
        ]);
    }
}
