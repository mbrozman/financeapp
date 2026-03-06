<?php

namespace App\Filament\Pages;

use App\Models\MonthlyIncome;
use App\Models\FinancialPlanItem;
use App\Models\Budget;
use App\Models\Transaction;
use Filament\Pages\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MonthlyBudget extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Mesačný rozpočet';
    protected static ?string $navigationGroup = 'Financie';
    protected static string $view = 'filament.pages.monthly-budget';

    public $selectedMonth;

    public function mount()
    {
        $this->selectedMonth = now()->format('Y-m');
    }

    public function getBudgetData(): array
    {
        $userId = auth()->id();
        $date = \Carbon\Carbon::parse($this->selectedMonth . '-01');

        // 1. Získame reálny príjem pre tento mesiac
        $income = \App\Models\MonthlyIncome::where('user_id', $userId)->where('period', $this->selectedMonth)->first();
        $incomeAmount = $income ? (float)$income->amount : 0;

        // 2. Získame piliere
        $pillars = \App\Models\FinancialPlanItem::whereHas('financialPlan', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->get();

        $groupedData = [];

        foreach ($pillars as $pillar) {
            // A. VÝPOČET LIMITU PILIERA (napr. 2200 * 25% = 550 €)
            $pillarLimit = ($incomeAmount * (float)$pillar->percentage) / 100;

            // B. ZÍSKAME KATEGÓRIE A ICH ČERPANIE
            $rules = \App\Models\Budget::where('user_id', $userId)
                ->where('financial_plan_item_id', $pillar->id)
                ->where('valid_from', '<=', $date->copy()->endOfMonth())
                ->get()
                ->groupBy('category_id');

            $pillarBudgets = [];
            $pillarTotalActual = 0;

            foreach ($rules as $catId => $categoryRules) {
                $activeRule = $categoryRules->sortByDesc('valid_from')->first();

                $actual = \App\Models\Transaction::where('category_id', $catId)
                    ->where('type', 'expense')
                    ->whereMonth('transaction_date', $date->month)
                    ->whereYear('transaction_date', $date->year)
                    ->sum('amount');

                $actualAbs = abs((float)$actual);
                $pillarTotalActual += $actualAbs; // Sčítavame do celku za pilier

                $pillarBudgets[] = [
                    'category' => $activeRule->category->name,
                    'limit' => (float)$activeRule->limit_amount,
                    'actual' => $actualAbs,
                    'percent' => (float)$activeRule->limit_amount > 0 ? ($actualAbs / (float)$activeRule->limit_amount) * 100 : 0,
                ];
            }

            // C. CELKOVÝ PERCENTUÁLNY STAV PILIERA
            $pillarPercent = $pillarLimit > 0 ? ($pillarTotalActual / $pillarLimit) * 100 : 0;

            $groupedData[] = [
                'pillar_name' => $pillar->name,
                'pillar_limit' => $pillarLimit,
                'pillar_actual' => $pillarTotalActual,
                'pillar_percent' => $pillarPercent,
                'budgets' => $pillarBudgets,
            ];
        }

        return [
            'pillars' => $groupedData,
            'income' => $incomeAmount
        ];
    }
    public function previousMonth()
    {
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->subMonth()->format('Y-m');
    }
    public function nextMonth()
    {
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->addMonth()->format('Y-m');
    }
}
