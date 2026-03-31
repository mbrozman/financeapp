<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Models\PortfolioSnapshot;
use Illuminate\Support\Carbon;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

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

        $startValueBD = BigDecimal::of((string)$startSnapshot->total_market_value_eur);
        if ($startValueBD->isZero()) {
            return null;
        }

        $endValueBD = BigDecimal::of((string)$endSnapshot->total_market_value_eur);

        // (($endValue / $startValue) - 1) * 100
        try {
            $ratio = $endValueBD->dividedBy($startValueBD, 4, RoundingMode::HALF_UP);
            $performance = $ratio->minus(1)->multipliedBy(100);
            return round($performance->toFloat(), 2);
        } catch (\Exception $e) {
            return null;
        }
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
        $endPriceBD = BigDecimal::of((string)$historyEnd->price);
        $endDate = $historyEnd->recorded_at;

        if ($endPriceBD->isZero()) return null;

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

        if (!$startPriceHistory) return null;

        $startPriceBD = BigDecimal::of((string)$startPriceHistory->price);
        
        if ($startPriceBD->isZero()) {
            return null;
        }

        try {
            $ratio = $endPriceBD->dividedBy($startPriceBD, 4, RoundingMode::HALF_UP);
            $performance = $ratio->minus(1)->multipliedBy(100);
            return round($performance->toFloat(), 2);
        } catch (\Exception $e) {
            return null;
        }
    }
}
