<?php

namespace App\Filament\Widgets;

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Models\PortfolioSnapshot; // PRIDANÉ
use Filament\Widgets\ChartWidget;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class PortfolioPerformanceChart extends ChartWidget
{
    protected static ?string $heading = 'Výkonnosť: Moje portfólio vs. S&P 500 (%)';
    protected static ?int $sort = 4;
    protected static ?string $maxHeight = '300px';

    protected function getFilters(): ?array
    {
        return [
            '30' => 'Posledný mesiac',
            '90' => 'Posledné 3 mesiace',
            '365' => 'Posledný rok',
        ];
    }

    protected function getData(): array
    {
        $days = (int) ($this->filter ?? 30);
        $userId = Auth::id();

        // 1. ZÍSKAME DÁTA ZO SNAPSHOTOV (Moje portfólio)
        $snapshots = PortfolioSnapshot::where('user_id', $userId)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at', 'asc')
            ->get();

        // 2. ZÍSKAME DÁTA PRE BENCHMARK (S&P 500)
        $benchmark = Investment::where('ticker', 'SPY')->first();
        $benchHistory = $benchmark
            ? InvestmentPriceHistory::where('investment_id', $benchmark->id)
            ->where('recorded_at', '>=', now()->subDays($days))
            ->orderBy('recorded_at', 'asc')
            ->get()
            ->keyBy(fn($item) => $item->recorded_at->format('Y-m-d'))
            : collect();

        $portfolioValues = [];
        $benchmarkValues = [];
        $labels = [];

        // Prvé hodnoty pre výpočet percent (Normalizácia na 100%)
        $firstPortfolioValue = null;
        $firstBenchPrice = null;

        foreach ($snapshots as $snapshot) {
            $dateKey = $snapshot->recorded_at->format('Y-m-d');

            // UX: Optimalizácia popiskov na osi X
            if ($days > 90) {
                $labels[] = $snapshot->recorded_at->dayOfWeek === 1 ? $snapshot->recorded_at->format('d.M') : '';
            } else {
                $labels[] = $snapshot->recorded_at->format('d.M');
            }

            // --- MOJE PORTFÓLIO ---
            $currentMarketValue = BigDecimal::of($snapshot->total_market_value_eur);
            if ($firstPortfolioValue === null && $currentMarketValue->isGreaterThan(0)) {
                $firstPortfolioValue = $currentMarketValue;
            }

            $portfolioValues[] = $firstPortfolioValue && $firstPortfolioValue->isGreaterThan(0)
                ? $currentMarketValue->dividedBy($firstPortfolioValue, 4, RoundingMode::HALF_UP)->multipliedBy(100)->toFloat()
                : 100;

            // --- BENCHMARK (SPY) ---
            $benchEntry = $benchHistory->get($dateKey);
            $currentBenchPrice = $benchEntry ? BigDecimal::of($benchEntry->price) : null;

            if ($firstBenchPrice === null && $currentBenchPrice && $currentBenchPrice->isGreaterThan(0)) {
                $firstBenchPrice = $currentBenchPrice;
            }

            $benchmarkValues[] = $firstBenchPrice && $currentBenchPrice
                ? $currentBenchPrice->dividedBy($firstBenchPrice, 4, RoundingMode::HALF_UP)->multipliedBy(100)->toFloat()
                : 100;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Moje portfólio (%)',
                    'data' => $portfolioValues,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => 'start',
                    'borderWidth' => 3,
                    'tension' => 0.3,
                    'pointRadius' => $days > 30 ? 0 : 3,
                ],
                [
                    'label' => 'Trh (S&P 500) (%)',
                    'data' => $benchmarkValues,
                    'borderColor' => '#94a3b8',
                    'borderDash' => [5, 5],
                    'borderWidth' => 2,
                    'pointRadius' => 0,
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public static function canView(): bool
    {
        return false;
    }
}
