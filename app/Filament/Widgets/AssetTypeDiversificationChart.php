<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Investment;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;

class AssetTypeDiversificationChart extends ChartWidget
{
    protected static string $view = 'filament.widgets.asset-type-diversification-chart';
    protected static ?int $sort = 2;
    protected static ?string $maxHeight = '300px';
    protected static bool $isCard = false;

    protected function getData(): array
    {
        $currencyCode = session('global_currency', 'EUR');
        $investments = Investment::with(['currency'])
            ->where('user_id', auth()->id())
            ->where('is_archived', false)
            ->get();

        $targetRate = CurrencyService::getRate($currencyCode);
        $targetRateBD = BigDecimal::of($targetRate);

        $aggregatedData = [];
        $typeLabels = [
            'Equity' => 'Akcie',
            'ETF' => 'ETF Fondy',
            'Crypto' => 'Kryptomeny',
            'Bond' => 'Dlhopisy',
            'Commodity' => 'Komodity',
            'Other' => 'Iné',
        ];

        foreach ($investments as $inv) {
            $type = $inv->asset_type ?: 'Other';
            $label = $typeLabels[$type] ?? $type;

            // Prepočet na EUR a následne na globálnu menu
            $valEur = CurrencyService::convertToEur((string)$inv->current_market_value_base, $inv->currency_id);
            $valTarget = BigDecimal::of($valEur)->multipliedBy($targetRateBD);
            $floatVal = (float)(string)$valTarget;

            if ($floatVal > 0) {
                $aggregatedData[$label] = ($aggregatedData[$label] ?? 0) + $floatVal;
            }
        }

        // Zotriedenie od najväčšieho podielu
        arsort($aggregatedData);

        $totalValue = array_sum($aggregatedData);
        $labelsWithPercentages = [];
        
        foreach ($aggregatedData as $label => $value) {
            $percentage = $totalValue > 0 ? round(($value / $totalValue) * 100, 1) : 0;
            $labelsWithPercentages[] = "$label ($percentage%)";
        }

        return [
            'datasets' => [
                [
                    'label' => 'Hodnota (' . $currencyCode . ')',
                    'data' => array_values($aggregatedData),
                    'backgroundColor' => [
                        '#3b82f6', // blue
                        '#10b981', // emerald
                        '#f59e0b', // amber
                        '#ef4444', // red
                        '#8b5cf6', // violet
                        '#06b6d4', // cyan
                    ],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labelsWithPercentages,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'cutout' => '70%',
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                        'font' => [
                            'size' => 11,
                            'weight' => 'bold',
                        ],
                    ],
                ],
                'tooltip' => [
                    'enabled' => true,
                    'padding' => 12,
                    'cornerRadius' => 8,
                ],
            ],
        ];
    }
}
