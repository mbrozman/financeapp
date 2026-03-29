<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use App\Models\InvestmentPriceHistory;
use Filament\Widgets\ChartWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Filament\Support\RawJs;

class IndividualInvestmentChart extends BaseWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 1;

    // Cache pre výpočty - aby sme nevolali DB dvakrát
    private ?array $cachedChartData = null;

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Celkovo',
            '12m' => 'Posledný rok',
            'ytd' => 'Tento rok (YTD)',
            '6m' => '6 mesiacov',
            '3m' => '3 mesiace',
            '1m' => 'Mesiac',
            '1w' => 'Týždeň',
            '1d' => 'Posledných 24h',
        ];
    }

    private function buildChartData(): array
    {
        if ($this->cachedChartData !== null) {
            return $this->cachedChartData;
        }

        if (!$this->record) {
            return $this->cachedChartData = ['datasets' => [], 'labels' => [], 'yMin' => 0, 'yMax' => 100];
        }

        $filter = $this->filter ?: 'all';

        // 1. CIEĽOVÁ MENA
        $currencyCode = session('global_currency') ?: $this->record->currency?->code ?: 'EUR';
        $targetCurrency = \App\Models\Currency::where('code', $currencyCode)->first();
        $symbol = $targetCurrency ? $targetCurrency->symbol : ($currencyCode === 'EUR' ? '€' : $currencyCode);

        // 2. HISTÓRIA CEN
        $query = InvestmentPriceHistory::where('investment_id', $this->record->id);

        switch ($filter) {
            case '1d': $query->where('recorded_at', '>=', now()->subDay()); break;
            case '1w': $query->where('recorded_at', '>=', now()->subWeek()); break;
            case '1m': $query->where('recorded_at', '>=', now()->subMonth()); break;
            case '3m': $query->where('recorded_at', '>=', now()->subMonths(3)); break;
            case '6m': $query->where('recorded_at', '>=', now()->subMonths(6)); break;
            case '12m': $query->where('recorded_at', '>=', now()->subYear()); break;
            case 'ytd': $query->where('recorded_at', '>=', now()->startOfYear()); break;
        }

        $history = $query->orderBy('recorded_at', 'asc')->get();
        // dd($history->count());
        $priceData = [];
        foreach ($history as $h) {
            $price = (float) $h->price;
            if ($currencyCode !== $this->record->currency?->code && $targetCurrency) {
                $price = (float) \App\Services\CurrencyService::convert(
                    (string)$price,
                    $this->record->currency_id,
                    $targetCurrency->id
                );
            } elseif ($currencyCode === 'EUR' && $this->record->currency?->code !== 'EUR') {
                $price = (float) \App\Services\CurrencyService::convertToEur((string)$price, $this->record->currency_id);
            }
            $priceData[] = round($price, 2);
        }

        $labels = $history->pluck('recorded_at')->map(function($date) use ($filter) {
            if (in_array($filter, ['1d', '1w'])) return $date->format('d.M H:i');
            if (in_array($filter, ['1m', '3m', '6m'])) return $date->format('d.M');
            return $date->format('M Y');
        })->toArray();

        // 2.5 FALLBACK PRE PRÁZDNU HISTÓRIU
        if (empty($priceData)) {
            $currentPrice = (float) $this->record->current_price;
            // Prepočet aktuálnej ceny ak je iná mena
            if ($currencyCode !== $this->record->currency?->code && $targetCurrency) {
                $currentPrice = (float) \App\Services\CurrencyService::convert(
                    (string)$currentPrice,
                    $this->record->currency_id,
                    $targetCurrency->id
                );
            }
            $priceData[] = round($currentPrice, 2);
            $labels[] = now()->format('d.M H:i');
        }

        // 3. PRIEMERNÁ NÁKUPNÁ CENA
        $avgPriceBase = (float)($this->record->average_buy_price ?? 0);
        $avgPriceDisplay = $avgPriceBase;

        if ($currencyCode !== $this->record->currency?->code) {
            if ($currencyCode === 'EUR') {
                $avgPriceDisplay = (float)($this->record->average_buy_price_eur ?? 0);
            } elseif ($targetCurrency) {
                $avgPriceDisplay = (float) \App\Services\CurrencyService::convert(
                    (string)$avgPriceBase,
                    $this->record->currency_id,
                    $targetCurrency->id
                );
            }
        }

        $avgPriceLine = array_fill(0, count($priceData), round($avgPriceDisplay, 2));

        // 4. Y-OS BOUNDS
        $allValues = array_merge($priceData, $avgPriceDisplay > 0 ? [$avgPriceDisplay] : []);
        if (empty($allValues)) {
            $yMin = 0;
            $yMax = 100;
        } else {
            $minPrice = min($allValues);
            $maxPrice = max($allValues);
            $padding = ($maxPrice - $minPrice) * 0.05;
            if ($padding <= 0) $padding = $maxPrice > 0 ? $maxPrice * 0.05 : 10;
            $yMin = max(0, $minPrice - $padding);
            $yMax = $maxPrice + $padding;
            if ($yMin >= $yMax) { $yMin -= 1; $yMax += 1; }
        }

        return [
            'datasets' => [
                [
                    'label' => "Trhová cena ({$symbol})",
                    'data' => $priceData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                    'pointRadius' => count($priceData) > 50 ? 0 : 3,
                ],
                [
                    'label' => "Nákupný priemer: " . number_format($avgPriceDisplay, 2, ',', ' ') . " {$symbol}",
                    'data' => $avgPriceLine,
                    'borderColor' => '#3b82f6',
                    'borderDash' => [5, 5],
                    'pointRadius' => 0,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
            'yMin' => $yMin,
            'yMax' => $yMax,
        ];
    }

    protected function getData(): array
    {
        $data = $this->buildChartData();
        return [
            'datasets' => $data['datasets'],
            'labels' => $data['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        $data = $this->buildChartData();
        $yMin = $data['yMin'];
        $yMax = $data['yMax'];

        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
            ],
            'scales' => [
                'y' => [
                    'min' => $yMin,
                    'max' => $yMax,
                    'beginAtZero' => false,
                    'display' => true,
                    'grid' => [
                        'display' => true,
                        'color' => 'rgba(156, 163, 175, 0.2)',
                    ],
                    'ticks' => [
                        'display' => true,
                        // 'callback' => RawJs::make("function(value) { return value.toLocaleString('sk-SK') + ' '; }"),
                        'font' => ['size' => 11],
                    ],
                ],
                'x' => [
                    'display' => true,
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}