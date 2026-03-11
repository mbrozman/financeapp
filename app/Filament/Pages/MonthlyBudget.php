<?php

namespace App\Filament\Pages;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\MonthlyIncome;
use App\Models\FinancialPlan;
use App\Models\InvestmentTransaction;
use App\Enums\TransactionType;
use App\Models\Category;
use Filament\Pages\Page;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MonthlyBudget extends Page
{
    public static function canAccess(): bool
    {
        return !auth()->user() || !auth()->user()->is_superadmin;
    }

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Mesačný rozpočet';
    protected static ?string $navigationGroup = 'Financie';
    protected static string $view = 'filament.pages.monthly-budget';

    public $selectedMonth;

    public function mount()
    {
        // Nastavíme aktuálny mesiac pri štarte
        $this->selectedMonth = now()->format('Y-m');
    }

    /**
     * HLAVNÝ VÝPOČET DÁT PRE STRÁNKU
     */
    public function getBudgetData(): array
    {
        $userId = Auth::id();
        $date = Carbon::parse($this->selectedMonth . '-01');

        // 1. REÁLNY PRÍJEM (Sčíta transakcie typu income za daný mesiac)
        $actualIncome = abs((float)Transaction::where('user_id', $userId)
            ->where('type', TransactionType::INCOME)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount'));
        
        // 2. PLÁNOVANÝ PRÍJEM (Z Finančného plánu)
        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();
        $plannedIncome = $plan ? (float)$plan->monthly_income : 2200;
        $incomeDiff = $actualIncome - $plannedIncome;

        // 3. CELKOVÁ REÁLNA SPOTREBA (Všetky výdavky z banky v danom mesiaci)
        $totalSpentAbs = abs((float)Transaction::where('user_id', $userId)
            ->where('type', TransactionType::EXPENSE)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->sum('amount'));

        // 4. CELKOVÉ INVESTÍCIE (Ak chceš sčítať aj tie)
        $totalInvested = (float)InvestmentTransaction::where('user_id', $userId)
            ->where('type', TransactionType::BUY)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->get()
            ->sum(fn($tx) => ($tx->quantity * $tx->price_per_unit) / ($tx->exchange_rate ?: 1));

        // 5. LOGIKA PILIEROV (ŠUFLÍKOV)
        $pillarData = [];
        if ($plan) {
            foreach ($plan->items as $item) {
                // Výpočet limitu piliera podľa percent z reálnej výplaty
                $pLimit = ($actualIncome * (float)$item->percentage) / 100;
                
                // Získame rozpis kategórií pod týmto pilierom
                $categoryBudgets = $this->getCategoryBudgets($item->id, $date);
                
                // --- TU JE OPRAVA: Master Bar sčíta presne to, čo majú kategórie pod ním ---
                $pActualAbs = collect($categoryBudgets)->sum('actual');
                
                $pillarData[] = [
                    'name' => $item->name,
                    'limit' => $pLimit,
                    'actual' => $pActualAbs,
                    'percent' => $pLimit > 0 ? ($pActualAbs / $pLimit) * 100 : 0,
                    'budgets' => $categoryBudgets
                ];
            }
        }

        // 6. LOGIKA DNÍ (Pre widget disciplíny)
        $now = now();
        $daysRemaining = ($now->format('Y-m') === $this->selectedMonth) 
            ? $now->diffInDays($now->copy()->endOfMonth()) + 1 
            : 0;

        return [
            'actual_income' => $actualIncome,
            'planned_income' => $plannedIncome,
            'income_diff' => $incomeDiff,
            'total_spent' => $totalSpentAbs,
            'total_invested' => $totalInvested,
            'savings' => $actualIncome - ($totalSpentAbs + $totalInvested),
            'pillars' => $pillarData,
            'days_remaining' => $daysRemaining,
        ];
    }

    /**
     * VÝPOČET KATEGÓRIÍ POD KONKRÉTNYM PILIEROM
     */
    protected function getCategoryBudgets($pillarId, $date): array
    {
        $userId = Auth::id();
        
        // Získame pravidlá rozpočtov priradené k tomuto pilieru
        $rules = Budget::where('user_id', $userId)
            ->where('financial_plan_item_id', $pillarId)
            ->get();

        $res = [];
        foreach ($rules as $rule) {
            // Získame ID hlavnej kategórie a všetkých jej podkategórií (napr. Bývanie + Nájom + Hypo)
            $categoryIds = Category::where('id', $rule->category_id)
                ->orWhere('parent_id', $rule->category_id)
                ->pluck('id');

            // Sčítame transakcie pre celú túto skupinu kategórií
            $actual = Transaction::whereIn('category_id', $categoryIds)
                ->whereMonth('transaction_date', $date->month)
                ->whereYear('transaction_date', $date->year)
                ->sum('amount');
                
            $actAbs = abs((float)$actual);
            $lim = (float)$rule->limit_amount;

            $res[] = [
                'category' => $rule->category->name ?? 'Neznáma',
                'actual' => $actAbs,
                'limit' => $lim,
                'percent' => $lim > 0 ? ($actAbs / $lim) * 100 : 0,
            ];
        }
        return $res;
    }

    // Navigačné metódy
    public function previousMonth() 
    { 
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->subMonth()->format('Y-m'); 
    }

    public function nextMonth() 
    { 
        $this->selectedMonth = Carbon::parse($this->selectedMonth . '-01')->addMonth()->format('Y-m'); 
    }
}
