<?php

namespace App\Filament\Widgets;

use App\Models\InvestmentCategory;
use Filament\Widgets\ChartWidget;

class InvestmentAllocationChart extends ChartWidget
{
    // Poradie na Dashboarde: 1. NetWorth, 2. Stats, 3. Tento graf
    protected static ?int $sort = 3;
    protected static ?string $heading = 'Diverzifikácia portfólia (v EUR)';
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // 1. EAGER LOADING
        // Načítame kategórie a k nim prislúchajúce nearchivované investície
        $categories = InvestmentCategory::with(['investments' => function($query) {
            $query->where('is_archived', false)->with(['transactions', 'currency']);
        }])->get();

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($categories as $category) {
            // 2. VÝPOČET HODNOTY KATEGÓRIE
            // Využijeme už hotový výpočet current_market_value_eur z modelu Investment
            $categoryValue = $category->investments->sum('current_market_value_eur');

            // Do grafu dáme len tie kategórie, v ktorých niečo reálne vlastníš
            if ($categoryValue > 0) {
                $labels[] = $category->name;
                $data[] = round($categoryValue, 2);
                $colors[] = $category->color ?? '#3b82f6'; // Farba, ktorú si si zvolil v Adminovi
            }
        }

        // 3. ŠTRUKTÚRA PRE CHART.JS
        return [
            'datasets' => [
                [
                    'label' => 'Hodnota v EUR',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'hoverOffset' => 20, // UX: Pri prejdení myšou sa kúsok grafu vysunie
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut'; // Prstencový graf vyzerá modernejšie ako klasický koláč (pie)
    }
}