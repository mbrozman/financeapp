<?php

namespace App\Filament\Pages;

use App\Models\BudgetRule;
use App\Models\Transaction;
use App\Models\MonthlyIncome;
use Filament\Pages\Page;
use Carbon\Carbon;

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

    /**
     * Výpočet dát pre grafy a karty v danom mesiaci
     */
    public function getBudgetData(): array
    {
        $date = Carbon::parse($this->selectedMonth . '-01');
        
        // Zoberieme tvoje TRVALÉ PRAVIDLÁ
        $rules = BudgetRule::with(['category', 'planItem'])->where('user_id', auth()->id())->get();
        
        // Zistíme reálnu výplatu pre tento konkrétny mesiac
        $income = MonthlyIncome::where('user_id', auth()->id())->where('period', $this->selectedMonth)->first();

        $results = [];

        foreach ($rules as $rule) {
            // Sčítame transakcie pre tento mesiac
            $actual = Transaction::where('category_id', $rule->category_id)
                ->where('type', 'expense')
                ->whereMonth('transaction_date', $date->month)
                ->whereYear('transaction_date', $date->year)
                ->sum('amount');

            $actualAbs = abs((float)$actual);
            $limit = (float)$rule->limit_amount;
            $percent = $limit > 0 ? ($actualAbs / $limit) * 100 : 0;

            $results[] = [
                'category' => $rule->category->name,
                'pillar' => $rule->planItem->name,
                'limit' => $limit,
                'actual' => $actualAbs,
                'percent' => $percent,
                'remaining' => $limit - $actualAbs,
            ];
        }

        return [
            'items' => $results,
            'income' => $income ? $income->amount : 0,
        ];
    }

    public function previousMonth() { $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->subMonth()->format('Y-m'); }
    public function nextMonth() { $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->addMonth()->format('Y-m'); }
}