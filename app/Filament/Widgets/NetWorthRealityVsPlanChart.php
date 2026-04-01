<?php

namespace App\Filament\Widgets;

use App\Services\DashboardFinanceService;
use Brick\Math\BigDecimal;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class NetWorthRealityVsPlanChart extends ChartWidget implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static ?int $sort = 5;
    protected static ?string $maxHeight = '400px';

    public ?string $filter = null;

    protected function getFilters(): ?array
    {
        return null;
    }

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable|null
    {
        return 'Mesačný rast: Realita vs. Plán (€)';
    }

    protected function getData(): array
    {
        $series = app(DashboardFinanceService::class)->getRealityVsPlanSeries(auth()->id(), $this->filter ?? 'with_roi');
        $labels = $series['labels'];
        $realityValues = $series['reality_values'];
        $modelValues = $series['model_values'];
        $isAhead = $series['is_ahead'];

        return [
            'datasets' => [
                [
                    'label' => 'Ušetrené v mesiaci (€)',
                    'data' => $series['monthly_saved_values'],
                    'backgroundColor' => 'rgba(99, 102, 241, 0.2)',
                    'borderColor' => '#6366f1',
                    'borderWidth' => 1,
                    'type' => 'bar',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Realita (€)',
                    'data' => $realityValues,
                    'borderColor' => $isAhead ? '#22c55e' : '#ef4444',
                    'backgroundColor' => $isAhead ? 'rgba(34,197,94,0.1)' : 'rgba(239,68,68,0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'pointRadius' => 4,
                    'type' => 'line',
                ],
                [
                    'label' => 'Model / Plán (€)',
                    'data' => $modelValues,
                    'borderColor' => '#94a3b8',
                    'backgroundColor' => 'transparent',
                    'borderDash' => [5, 5],
                    'tension' => 0.4,
                    'fill' => false,
                    'pointRadius' => 3,
                    'type' => 'line',
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
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'y' => ['beginAtZero' => false],
            ],
        ];
    }

    public static function canView(): bool
    {
        return true;
    }
}
