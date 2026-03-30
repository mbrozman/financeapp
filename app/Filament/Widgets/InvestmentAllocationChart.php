<?php

namespace App\Filament\Widgets;

use App\Models\InvestmentCategory;
use Filament\Widgets\ChartWidget;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

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
        $categories = InvestmentCategory::with(['investments' => function ($query) {
            $query->where('is_archived', false)->with(['transactions', 'currency']);
        }])->get();

        $data = [];
        $labels = [];
        $colors = [];

        foreach ($categories as $category) {
            $categoryValue = BigDecimal::of(0);

            foreach ($category->investments as $investment) {
                $categoryValue = $categoryValue->plus($investment->current_market_value_eur);
            }

            if ($categoryValue->isGreaterThan(0)) {
                $labels[] = $category->name;
                $data[] = $categoryValue->toFloat(); // Pre Chart.js
                $colors[] = $category->color ?? '#3b82f6';
            }
        }

        // 2. PRIDANIE HOTOVOSTI U BROKEROV
        $brokerAccounts = \App\Models\Account::where('user_id', auth()->id())
            ->where('type', 'investment')
            ->get();

        $totalBrokerCash = BigDecimal::of(0);
        foreach ($brokerAccounts as $acc) {
            $totalBrokerCash = $totalBrokerCash->plus(
                \App\Services\CurrencyService::convertToEur((string)$acc->balance, $acc->currency_id)
            );
        }

        if ($totalBrokerCash->isGreaterThan(0)) {
            $labels[] = 'Hotovosť u brokerov';
            $data[] = $totalBrokerCash->toFloat();
            $colors[] = '#94a3b8'; // Bridlicová šedá pre cash
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

    public static function canView(): bool
    {
        return false;
    }
}
