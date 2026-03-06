<?php

namespace App\Filament\Pages;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\MonthlyIncome;
use App\Models\FinancialPlan;
use App\Models\FinancialPlanItem;
use App\Models\Category;
use Filament\Pages\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

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

    // 1. Príjem
    $incomeRecord = \App\Models\MonthlyIncome::where('user_id', $userId)->where('period', $this->selectedMonth)->first();
    $actualIncome = $incomeRecord ? (float)$incomeRecord->amount : 0;
    
    $plan = \App\Models\FinancialPlan::where('user_id', $userId)->first();
    $plannedIncome = $plan ? (float)$plan->monthly_income : 2200;

    // 2. Bežná spotreba (Transakcie)
    $totalSpent = \App\Models\Transaction::where('user_id', $userId)
        ->where('type', 'expense')
        ->whereMonth('transaction_date', $date->month)
        ->whereYear('transaction_date', $date->year)
        ->sum('amount');
    $totalSpentAbs = abs((float)$totalSpent);

    // 3. Investovaná suma (Nákupy akcií) - TOTO TI CHÝBALO
    $totalInvested = \App\Models\InvestmentTransaction::where('user_id', $userId)
        ->where('type', \App\Enums\TransactionType::BUY)
        ->whereMonth('transaction_date', $date->month)
        ->whereYear('transaction_date', $date->year)
        ->get()
        ->sum(fn($tx) => ($tx->quantity * $tx->price_per_unit) / ($tx->exchange_rate ?: 1));

    // 4. Výsledok
    $savings = $actualIncome - ($totalSpentAbs + $totalInvested);

    // 5. Piliere (Zjednodušená verzia pre tento príklad)
    $pillarData = [];
    if ($plan) {
        foreach ($plan->load('items')->items as $item) {
            $pLimit = ($actualIncome * (float)$item->percentage) / 100;
            $catIds = \App\Models\Category::where('financial_plan_item_id', $item->id)->pluck('id');
            $pActual = abs((float)\App\Models\Transaction::whereIn('category_id', $catIds)->whereMonth('transaction_date', $date->month)->whereYear('transaction_date', $date->year)->sum('amount'));
            
            $pillarData[] = [
                'name' => $item->name,
                'limit' => $pLimit,
                'actual' => $pActual,
                'percent' => $pLimit > 0 ? ($pActual / $pLimit) * 100 : 0,
                'budgets' => [] // Tu si doplň getCategoryBudgets ak ho používaš
            ];
        }
    }

    return [
        'actual_income' => $actualIncome,
        'planned_income' => $plannedIncome,
        'income_diff' => $actualIncome - $plannedIncome,
        'total_spent' => $totalSpentAbs,
        'total_invested' => $totalInvested, // TENTO KĽÚČ TU MUSÍ BYŤ
        'savings' => $savings,
        'pillars' => $pillarData,
    ];
}

    protected function getCategoryBudgets($pillarId, $date): array
    {
        $rules = Budget::where('financial_plan_item_id', $pillarId)->get();
        $res = [];
        foreach ($rules as $rule) {
            $act = Transaction::where('category_id', $rule->category_id)
                ->whereMonth('transaction_date', $date->month)
                ->whereYear('transaction_date', $date->year)
                ->sum('amount');
            $actAbs = abs((float)$act);
            $lim = (float)$rule->limit_amount;
            $res[] = [
                'category' => $rule->category->name ?? 'Zmazaná kategória',
                'actual' => $actAbs,
                'limit' => $lim,
                'percent' => $lim > 0 ? ($actAbs / $lim) * 100 : 0,
            ];
        }
        return $res;
    }

    public function previousMonth() { $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->subMonth()->format('Y-m'); }
    public function nextMonth() { $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->addMonth()->format('Y-m'); }
}