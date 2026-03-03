<?php

namespace App\Filament\Widgets;

use App\Models\Investment;
use App\Models\InvestmentPriceHistory;
use App\Models\InvestmentTransaction;
use App\Services\CurrencyService; // PRIDANÉ
use Filament\Widgets\ChartWidget;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PortfolioPerformanceChart extends ChartWidget
{
    protected static ?string $heading = 'Výkonnosť: Moje portfólio vs. S&P 500 (%)';
    protected static ?int $sort = 4;
    protected static ?string $maxHeight = '300px';

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Posledný týždeň',
            '30' => 'Posledný mesiac',
            '90' => 'Posledné 3 mesiace',
            '180' => 'Posledných 6 mesiacov',
            '365' => 'Posledný rok',
        ];
    }

    protected function getData(): array
    {
        $daysToLookBack = (int) ($this->filter ?? 30);
        $period = CarbonPeriod::create(now()->subDays($daysToLookBack), now());

        $portfolioValues = [];
        $benchmarkValues = [];
        $labels = [];

        // 1. ZÍSKAME REÁLNY KURZ Z NAŠEJ SLUŽBY
        $usdRate = CurrencyService::getLiveRate('USD');

        $investments = Investment::with(['transactions']) // PRIDANÉ with
            ->where('user_id', Auth::id())
            ->where('ticker', '!=', 'SPY')
            ->where('is_archived', false)
            ->get();

        $benchmarkRecord = Investment::where('ticker', 'SPY')->first();

        $firstPortfolioValue = null;
        $firstBenchmarkPrice = null;

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');

            // UX: Optimalizácia popiskov na osi X
            if ($daysToLookBack > 90) {
                $labels[] = $date->dayOfWeek === 1 ? $date->format('d.M') : '';
            } else {
                $labels[] = $date->format('d.M');
            }

            // 1. VÝPOČET PORTFÓLIA (v EUR)
            $dailyTotalEur = 0;
            foreach ($investments as $investment) {
                // Zistíme koľko kusov sme mali v daný deň
                $qtyAtDate = InvestmentTransaction::where('investment_id', $investment->id)
                    ->where('transaction_date', '<=', $dateString)
                    ->sum(DB::raw("CASE WHEN type = 'buy' THEN quantity ELSE -quantity END"));

                if ($qtyAtDate <= 0) continue;

                // Zistíme historickú cenu v ten deň
                $priceEntry = InvestmentPriceHistory::where('investment_id', $investment->id)
                    ->where('recorded_at', '<=', $dateString)
                    ->orderBy('recorded_at', 'desc')->first();

                if ($priceEntry) {
                    $dailyTotalEur += ($qtyAtDate * ((float)$priceEntry->price / $usdRate));
                }
            }

            // 2. VÝPOČET BENCHMARKU (Cena SPY)
            $benchEntry = InvestmentPriceHistory::where('investment_id', $benchmarkRecord?->id)
                ->where('recorded_at', '<=', $dateString)
                ->orderBy('recorded_at', 'desc')->first();
            $benchPrice = $benchEntry ? (float)$benchEntry->price : 0;

            // 3. NORMALIZÁCIA (Nastavenie prvého dňa na 100%)
            if ($dailyTotalEur > 0 && $firstPortfolioValue === null) $firstPortfolioValue = $dailyTotalEur;
            if ($benchPrice > 0 && $firstBenchmarkPrice === null) $firstBenchmarkPrice = $benchPrice;

            $portfolioValues[] = $firstPortfolioValue ? round(($dailyTotalEur / $firstPortfolioValue) * 100, 2) : 100;
            $benchmarkValues[] = $firstBenchmarkPrice ? round(($benchPrice / $firstBenchmarkPrice) * 100, 2) : 100;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Moje portfólio (%)',
                    'data' => $portfolioValues,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Jemná modrá výplň
                    'fill' => 'start',
                    'borderWidth' => 3,
                    'tension' => 0.3,
                    'pointRadius' => $daysToLookBack > 30 ? 0 : 3,
                ],
                [
                    'label' => 'Trh (S&P 500)',
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
}
