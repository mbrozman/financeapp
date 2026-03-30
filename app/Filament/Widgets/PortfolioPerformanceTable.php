<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Services\InvestmentPerformanceService;

class PortfolioPerformanceTable extends Widget
{
    protected static string $view = 'filament.widgets.portfolio-performance-table';

    protected static ?int $sort = 2;

    public function getComparisonData()
    {
        return app(InvestmentPerformanceService::class)->getComparisonData(auth()->id() ?? 1);
    }
}
