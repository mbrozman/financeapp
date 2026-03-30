<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Models\PortfolioSnapshot;
use Illuminate\Support\Carbon;

class InvestmentPerformanceService
{
    public function getComparisonData($userId): array
    {
        $periods = [
            '1D' => now()->subDay(),
            '1W' => now()->subWeek(),
            '1M' => now()->subMonth(),
            '3M' => now()->subMonths(3),
            '6M' => now()->subMonths(6),
            '1Y' => now()->subYear(),
            'YTD' => now()->startOfYear(),
        ];

        $comparison = [
            ['label' => 'Moje Portfólio', 'ticker' => 'portfolio', 'data' => []],
            ['label' => 'S&P 500', 'ticker' => 'SPY', 'data' => []],
            ['label' => 'NASDAQ', 'ticker' => 'QQQ', 'data' => []],
        ];

        foreach ($periods as $label => $date) {
            $comparison[0]['data'][$label] = $this->calculatePortfolioReturn($userId, $date, $label);
            $comparison[1]['data'][$label] = $this->calculateBenchmarkReturn('SPY', $date, $label);
            $comparison[2]['data'][$label] = $this->calculateBenchmarkReturn('QQQ', $date, $label);
        }

        return $comparison;
    }

    private function calculatePortfolioReturn($userId, Carbon $startDate, string $label = ''): ?float
    {
        $endSnapshot = PortfolioSnapshot::where('user_id', $userId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (!$endSnapshot) return null;
        $endDate = $endSnapshot->recorded_at;

        if ($label === '1D') {
            $startSnapshot = PortfolioSnapshot::where('user_id', $userId)
                ->whereDate('recorded_at', '<', $endDate->toDateString())
                ->orderBy('recorded_at', 'desc')
                ->first();
        } else {
            $startSnapshot = PortfolioSnapshot::where('user_id', $userId)
                ->where('recorded_at', '<=', $startDate)
                ->orderBy('recorded_at', 'desc')
                ->first();
        }

        if (!$startSnapshot || (float)$startSnapshot->total_market_value_eur <= 0) {
            return null;
        }

        $endValue = (float)$endSnapshot->total_market_value_eur;
        $startValue = (float)$startSnapshot->total_market_value_eur;

        return (($endValue / $startValue) - 1) * 100;
    }

    private function calculateBenchmarkReturn(string $ticker, Carbon $startDate, string $label = ''): ?float
    {
        $investment = Investment::withoutGlobalScopes()
            ->where('ticker', $ticker)
            ->where('is_benchmark', true)
            ->first();

        if (!$investment) return null;

        // 1. Získať najčerstvejšiu cenu (End Price)
        $historyEnd = InvestmentPriceHistory::where('investment_id', $investment->id)
            ->orderBy('recorded_at', 'desc')
            ->first();
            
        if (!$historyEnd) return null;
        $endPrice = (float)$historyEnd->price;
        $endDate = $historyEnd->recorded_at;

        if ($endPrice <= 0) return null;

        // 2. Získať historickú cenu (Start Price)
        // Ak ide o 1D, chceme presne predchádzajúci dostupný záznam z histórie
        if ($label === '1D') {
            $startPriceHistory = InvestmentPriceHistory::where('investment_id', $investment->id)
                ->whereDate('recorded_at', '<', $endDate->toDateString())
                ->orderBy('recorded_at', 'desc')
                ->first();
        } else {
            $startPriceHistory = InvestmentPriceHistory::where('investment_id', $investment->id)
                ->where('recorded_at', '<=', $startDate)
                ->orderBy('recorded_at', 'desc')
                ->first();
        }

        if (!$startPriceHistory || (float)$startPriceHistory->price <= 0) {
            return null;
        }

        $startPrice = (float)$startPriceHistory->price;

        return (($endPrice / $startPrice) - 1) * 100;
    }
}
