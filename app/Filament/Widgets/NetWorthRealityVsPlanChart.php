<?php

namespace App\Filament\Widgets;

use App\Services\DashboardFinanceService;
use Filament\Widgets\ChartWidget;

class NetWorthRealityVsPlanChart extends ChartWidget
{
    protected static ?string $heading = 'Mesačný rast: Realita vs. Plán (€)';
    protected static ?int $sort = 1;
    protected static ?string $maxHeight = '800px';
    
    public ?string $filter = 'with_roi';

    protected function getFilters(): ?array
    {
        return [
            'with_roi' => 'S výnosom investícií (8%)',
            'pure_savings' => 'Iba čisté vklady z platu',
        ];
    }

    protected function getData(): array
    {
        $series = app(DashboardFinanceService::class)->getRealityVsPlanSeries((int) auth()->id(), $this->filter ?? 'with_roi');
        $labels = $series['labels'];
        $realityValues = $series['reality_values'];
        $modelValues = $series['model_values'];
        $isAhead = $series['is_ahead'];

        if (count($labels) === 0) {
            return ['datasets' => [], 'labels' => []];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Skutočný majetok (Snapshot)',
                    'data' => $realityValues,
                    'borderColor' => $isAhead ? '#22c55e' : '#ef4444',
                    'backgroundColor' => $isAhead ? 'rgba(34, 197, 94, 0.1)' : 'rgba(239, 68, 68, 0.1)',
                    'fill' => 'start',
                    'borderWidth' => 4,
                    'tension' => 0.2,
                    'spanGaps' => true,
                ],
                [
                    'label' => 'Ideálna cesta (Tvoj plán)',
                    'data' => $modelValues,
                    'borderColor' => '#94a3b8',
                    'borderDash' => [10, 5],
                    'backgroundColor' => 'transparent',
                    'borderWidth' => 2,
                    'tension' => 0.2,
                ],
            ],
            'labels' => $labels,
        ];
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
