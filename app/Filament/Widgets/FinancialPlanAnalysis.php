<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class FinancialPlanAnalysis extends ChartWidget
{
    public ?string $filter = null;

    public function getHeading(): ?string
    {
        $date = $this->filter ? \Carbon\Carbon::parse($this->filter) : now();
        return 'Analýza finančného plánu (' . $date->translatedFormat('F Y') . ')';
    }

    protected function getFilters(): ?array
    {
        $filters = [];
        $now = now()->startOfMonth();
        
        for ($i = 0; $i < 12; $i++) {
            $month = $now->copy()->subMonths($i);
            $filters[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }
        
        return $filters;
    }

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $userId = auth()->id();
        $selectedDate = $this->filter ? \Carbon\Carbon::parse($this->filter) : now();
        $targetMonth = $selectedDate->month;
        $targetYear = $selectedDate->year;
        
        // 1. Získame aktívny plán
        $plan = \App\Models\FinancialPlan::with('items')->where('user_id', $userId)->first();
        
        if (!$plan) {
            return ['datasets' => [], 'labels' => []];
        }

        // 2. Skutočný príjem za vybraný mesiac
        $actualIncome = \App\Models\Transaction::where('user_id', $userId)
            ->where('type', 'income')
            ->whereMonth('transaction_date', $targetMonth)
            ->whereYear('transaction_date', $targetYear)
            ->sum('amount');

        // Ak nemáme príjem, použijeme plánovaný mesačný príjem ako základ pre %, aby sme nezačínali nulou
        $incomeBase = (float)($actualIncome > 0 ? $actualIncome : $plan->monthly_income);

        $labels = [];
        $plannedData = [];
        $actualData = [];

        foreach ($plan->items->sortBy('percentage', descending: true) as $item) {
            $labels[] = $item->name;
            
            // Plánovaná hodnota (%)
            $plannedData[] = (float) $item->percentage;

            // Skutočná hodnota (%)
            // Sčítame všetky výdavky v kategóriách, ktoré patria pod toto "vrecúško"
            $actualSpent = \App\Models\Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->whereMonth('transaction_date', $targetMonth)
                ->whereYear('transaction_date', $targetYear)
                ->whereHas('category', function ($query) use ($item) {
                    $query->where('financial_plan_item_id', $item->id);
                })
                ->sum('amount');

            $actualSpent = abs($actualSpent);
            $actualData[] = round(($actualSpent / $incomeBase) * 100, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Plánované (%)',
                    'data' => $plannedData,
                    'backgroundColor' => 'rgba(148, 163, 184, 0.2)', // Slate transparent
                    'borderColor' => '#94a3b8',
                    'pointBackgroundColor' => '#94a3b8',
                ],
                [
                    'label' => 'Skutočnosť (%)',
                    'data' => $actualData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.3)', // Success transparent
                    'borderColor' => '#22c55e',
                    'pointBackgroundColor' => '#22c55e',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'radar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
        ];
    }

    public static function canView(): bool
    {
        return false;
    }
}
