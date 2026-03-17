<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Investment;
use Livewire\Attributes\Reactive;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;

class InvestmentDiversificationChart extends ChartWidget
{
    protected static ?string $heading = 'Rozloženie portfólia (Diverzifikácia)';
    protected static ?int $sort = 2;

    protected function getCurrency(): string
    {
        return session('global_currency', 'EUR');
    }

    public ?string $grouping = 'asset_type';

    public function getHeading(): ?string
    {
        return match ($this->grouping) {
            'sector' => 'Diverzifikácia podľa sektorov',
            'country' => 'Diverzifikácia podľa krajín',
            default => 'Diverzifikácia podľa triedy aktív',
        };
    }

    protected function getData(): array
    {
        $investments = Investment::with(['transactions', 'currency'])
            ->where('user_id', auth()->id())
            ->where('is_archived', false) // Len aktívne investicie pre koláčový graf
            ->get();

        $groupingField = $this->grouping; // 'asset_type', 'sector', alebo 'country'
        $targetRate = CurrencyService::getRate($this->getCurrency());
        $targetRateBD = BigDecimal::of($targetRate);

        $aggregatedData = [];

        foreach ($investments as $inv) {
            $key = $inv->{$groupingField} ?: 'Nezaradené';

            // Trhová hodnota báza
            $valBase = $inv->current_market_value_base;
            
            // Konverzia na EUR a potom na cieľovú menu (ak $currency nie je EUR)
            $valEur = CurrencyService::convertToEur((string)$valBase, $inv->currency_id);
            $valTarget = BigDecimal::of($valEur)->multipliedBy($targetRateBD);
            $floatVal = (float)(string)$valTarget;

            if ($floatVal > 0) {
                if (!isset($aggregatedData[$key])) {
                    $aggregatedData[$key] = 0;
                }
                $aggregatedData[$key] += $floatVal;
            }
        }

        // Zotriedime od najväčšej položky po najmenšiu
        arsort($aggregatedData);

        return [
            'datasets' => [
                [
                    'label' => 'Hodnota (' . $this->getCurrency() . ')',
                    'data' => array_values($aggregatedData),
                    'backgroundColor' => [
                        '#3b82f6', // blue-500
                        '#10b981', // emerald-500
                        '#f59e0b', // amber-500
                        '#ef4444', // red-500
                        '#8b5cf6', // violet-500
                        '#06b6d4', // cyan-500
                        '#f97316', // orange-500
                        '#ec4899', // pink-500
                        '#64748b', // slate-500
                        '#14b8a6', // teal-500
                    ],
                ],
            ],
            'labels' => array_keys($aggregatedData),
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 15,
                    ],
                ],
            ],
            'cutout' => '70%',
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
