<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;

class PillarPerformanceWidget extends Widget
{
    protected static string $view = 'filament.widgets.pillar-performance-widget';

    public string $period;

    protected static ?int $sort = 2; // Above the charts

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

        $income = $plan->monthly_income;
        $items = FinancialPlanItem::where('financial_plan_id', $plan->id)->get();
        
        $year = substr($this->period, 0, 4);
        $month = substr($this->period, 5, 2);
        
        // Pred-načítame transakcie za daný mesiac pre rýchlejší výpočet
        $transactions = Transaction::where('user_id', $userId)
            ->where('type', 'expense')
            ->whereYear('transaction_date', $year)
            ->whereMonth('transaction_date', $month)
            ->with('category')
            ->get();

        $data = [];
        $totalExpenses = 0;
        $targetSavings = 0;

        foreach ($items as $item) {
            $modelLimit = $income * ($item->percentage / 100);
            
            if ($item->contributes_to_net_worth) {
                $targetSavings += $modelLimit;
            }

            // Konfigurovaný limit: súčet monthly_limit z root kategórií priradených k tomuto pilieru
            $configuredLimit = Category::where('user_id', $userId)
                ->where('financial_plan_item_id', $item->id)
                ->whereNull('parent_id')
                ->sum('monthly_limit');

            // Reálne minuté: suma transakcií v kategóriách patriacich pod tento pilier
            $actualSpent = $transactions->filter(function ($t) use ($item) {
                return $t->category && $t->category->financial_plan_item_id == $item->id;
            })->sum('amount');

            $actualSpentAbs = abs((float) $actualSpent);
            $totalExpenses += $actualSpentAbs;

            $data[] = [
                'name' => $item->name,
                'percentage' => $item->percentage,
                'model_limit' => (float) $modelLimit,
                'configured_limit' => (float) $configuredLimit,
                'actual_spent' => $actualSpentAbs,
                'color' => $this->getPillarColor($item->name),
                'is_savings' => $item->contributes_to_net_worth,
            ];
        }

        $actualSavings = $income - $totalExpenses;
        $savingsSurplus = $actualSavings - $targetSavings;

        return [
            'pillars' => $data,
            'summary' => [
                'income' => (float) $income,
                'total_expenses' => (float) $totalExpenses,
                'target_savings' => (float) $targetSavings,
                'actual_savings' => (float) $actualSavings,
                'savings_surplus' => (float) $savingsSurplus,
            ]
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

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.widgets.pillar-performance-widget', [
            'pillars' => $this->getPillarData(),
        ]);
    }
}
