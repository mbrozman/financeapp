<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Models\PortfolioSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PortfolioBenchmarkChart extends ChartWidget
{
    protected static ?string $heading = 'Výkonnosť vs Benchmarky (TWR)';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '700px';

    public ?string $filter = '12'; // Default 12 months

    protected function getFilters(): ?array
    {
        return [
            '1' => 'Posledný mesiac',
            '3' => 'Posledné 3 mesiace',
            '12' => 'Posledných 12 mesiacov',
            'ytd' => 'Tento rok (YTD)',
            'last_year' => 'Minulý kalendárny rok',
            'all' => 'Celkovo',
        ];
    }

    protected function getData(): array
    {
        $userId = auth()->id();
        $startDate = now()->subMonths(12)->startOfMonth();
        $endDate = null;

        switch ($this->filter) {
            case '1': $startDate = now()->subMonth(); break;
            case '3': $startDate = now()->subMonths(3); break;
            case '12': $startDate = now()->subMonths(12); break;
            case 'ytd': $startDate = now()->startOfYear(); break;
            case 'last_year': 
                $startDate = now()->subYear()->startOfYear(); 
                $endDate = now()->subYear()->endOfYear();
                break;
            case 'all': 
                $firstSnapshot = PortfolioSnapshot::where('user_id', $userId)->orderBy('recorded_at')->first();
                $startDate = $firstSnapshot ? $firstSnapshot->recorded_at : now()->subYear();
                break;
        }

        // 1. Get Portfolio Cumulative Returns (based on Snapshots)
        // We do this first because it determines the effective start date of the chart
        $portfolioHistory = $this->getPortfolioNormalizedHistory($userId, $startDate, $endDate);

        if (empty($portfolioHistory)) {
            return ['datasets' => [], 'labels' => []];
        }

        // 2. Determine actual start date from first available snapshot
        $labels = array_keys($portfolioHistory);
        $firstChartDate = Carbon::parse($labels[0]);

        // 3. Get Benchmarks (SPY and QQQ) normalized to the CHART start date
        $spy = Investment::where('ticker', 'SPY')->first();
        $qqq = Investment::where('ticker', 'QQQ')->first();

        $spyHistory = $spy ? $this->getNormalizedHistory($spy->id, $firstChartDate, $endDate) : [];
        $qqqHistory = $qqq ? $this->getNormalizedHistory($qqq->id, $firstChartDate, $endDate) : [];

        return [
            'datasets' => [
                [
                    'label' => 'Moje Portfólio (%)',
                    'data' => array_values($portfolioHistory),
                    'borderColor' => '#3b82f6', // Blue
                    'backgroundColor' => '#3b82f633',
                    'fill' => false,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'S&P 500 (SPY) (%)',
                    'data' => $this->alignData($labels, $spyHistory),
                    'borderColor' => '#ef4444', // Red
                    'fill' => false,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Nasdaq 100 (QQQ) (%)',
                    'data' => $this->alignData($labels, $qqqHistory),
                    'borderColor' => '#f59e0b', // Amber
                    'fill' => false,
                    'tension' => 0.4,
                ],
            ],
            'labels' => array_map(fn($date) => Carbon::parse($date)->format('d.m.Y'), $labels),
        ];
    }

    private function getNormalizedHistory(int $investmentId, Carbon $startDate, ?Carbon $endDate = null): array
    {
        $query = InvestmentPriceHistory::where('investment_id', $investmentId)
            ->where('recorded_at', '>=', $startDate);

        if ($endDate) {
            $query->where('recorded_at', '<=', $endDate);
        }

        $history = $query->orderBy('recorded_at')->get();

        if ($history->isEmpty()) return [];

        $firstPrice = (float) $history->first()->price;
        $normalized = [];

        foreach ($history as $row) {
            $currentPrice = (float) $row->price;
            // % change from start
            $normalized[$row->recorded_at->toDateString()] = $firstPrice > 0 
                ? round((($currentPrice - $firstPrice) / $firstPrice) * 100, 2)
                : 0;
        }

        return $normalized;
    }

    private function getPortfolioNormalizedHistory(int $userId, Carbon $startDate, ?Carbon $endDate = null): array
    {
        $query = PortfolioSnapshot::where('user_id', $userId)
            ->where('recorded_at', '>=', $startDate);

        if ($endDate) {
            $query->where('recorded_at', '<=', $endDate);
        }

        $snapshots = $query->orderBy('recorded_at')->get();

        if ($snapshots->isEmpty()) return [];

        $firstValue = (float) $snapshots->first()->total_market_value_eur;
        $normalized = [];

        foreach ($snapshots as $snap) {
            $currentValue = (float) $snap->total_market_value_eur;
            // Approximation: % change from start
            // Note: True TWR requires cash flow adjustments per period, 
            // but for this visualization we show cumulative equity curve.
            $normalized[$snap->recorded_at->toDateString()] = $firstValue > 0
                ? round((($currentValue - $firstValue) / $firstValue) * 100, 2)
                : 0;
        }

        return $normalized;
    }

    private function alignData(array $labels, array $history): array
    {
        $data = [];
        $lastValue = 0;

        foreach ($labels as $date) {
            if (isset($history[$date])) {
                $lastValue = $history[$date];
            }
            $data[] = $lastValue;
        }

        return $data;
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
