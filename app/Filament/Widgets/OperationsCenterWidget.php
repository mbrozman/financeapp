<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Models\Transaction;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class OperationsCenterWidget extends Widget
{
    protected static string $view = 'filament.widgets.operations-center-widget';

    protected static ?int $sort = 1; // At the very top

    protected int | string | array $columnSpan = 'full';

    public function getData(): array
    {
        $userId = Auth::id();
        $now = now();
        $plan = FinancialPlan::where('user_id', $userId)->where('is_active', true)->first();

        if (!$plan) {
            return ['pillars' => [], 'top_expenses' => [], 'overflow' => false];
        }

        $income = $plan->monthly_income;
        $items = FinancialPlanItem::where('financial_plan_id', $plan->id)->get();
        
        // Fetch all transactions for this month once
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereYear('transaction_date', $now->year)
            ->whereMonth('transaction_date', $now->month)
            ->with('category')
            ->get();

        $pillars = [];
        $pillar1Remaining = 0;
        $hasSavingsPillar = false;

        foreach ($items as $item) {
            $modelLimit = $income * ($item->percentage / 100);
            
            // Actual spent in this pillar
            $actualSpent = $transactions->filter(function ($t) use ($item) {
                return $t->category && $t->category->financial_plan_item_id == $item->id;
            })->sum('amount');

            $actualSpent = abs((float) $actualSpent);
            $remaining = $modelLimit - $actualSpent;
            $percentage = $modelLimit > 0 ? ($actualSpent / $modelLimit) * 100 : 0;

            $pillarData = [
                'name' => $item->name,
                'remaining' => $remaining,
                'percentage' => min(100, $percentage),
                'actual_spent' => $actualSpent,
                'model_limit' => $modelLimit,
                'color' => $this->getPillarColor($item->name),
                'is_essential' => str_contains($item->name, 'HLAVNÉ'),
                'is_reserve' => str_contains($item->name, 'REZERVA'),
            ];

            if ($pillarData['is_essential']) {
                $pillar1Remaining = $remaining;
            }
            if ($pillarData['is_reserve']) {
                $hasSavingsPillar = true;
            }

            $pillars[] = $pillarData;
        }

        // Top 5 Expenses
        $topExpenses = $transactions->sortByDesc('amount')->take(5)->map(function ($t) {
            return [
                'name' => $t->description ?: ($t->category?->name ?: 'Bez kategórie'),
                'amount' => (float) $t->amount,
                'date' => $t->transaction_date->format('d.m.'),
                'category' => $t->category?->name,
                'color' => $t->category?->color ?: '#94a3b8',
            ];
        })->values()->toArray();

        return [
            'pillars' => $pillars,
            'top_expenses' => $topExpenses,
            'overflow' => $pillar1Remaining > 0 && $hasSavingsPillar,
            'month_name' => strtolower($now->translatedFormat('F')),
        ];
    }

    protected function getPillarColor(string $name): string
    {
        if (str_contains($name, 'HLAVNÉ')) return '#ef4444'; // Red
        if (str_contains($name, 'INVESTOVANIE')) return '#3b82f6'; // Blue
        if (str_contains($name, 'REZERVA')) return '#eab308'; // Yellow
        if (str_contains($name, 'VRECKOVÉ')) return '#22c55e'; // Green
        return '#94a3b8';
    }
}
