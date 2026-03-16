<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use App\Models\InvestmentPriceHistory;
use Filament\Widgets\ChartWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

class IndividualInvestmentChart extends BaseWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        if (!$this->record) return ['datasets' => [], 'labels' => []];

        // 1. ZÍSKANIE CIEĽOVEJ MENY - Používame natívnu menu investície
        $targetCurrency = $this->record->currency;
        $symbol = $targetCurrency->symbol ?? '$';

        // 2. ZÍSKANIE HISTÓRIE CIEN
        $history = InvestmentPriceHistory::where('investment_id', $this->record->id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        $priceData = $history->pluck('price')->toArray();
        $labels = $history->pluck('recorded_at')->map(fn($date) => $date->format('d.M'))->toArray();

        // 3. PRIEMERNÁ NÁKUPKA
        $avgPrice = (float) $this->record->average_buy_price_base;
        $avgPriceLine = array_fill(0, count($priceData), $avgPrice);

        return [
            'datasets' => [
                [
                    'label' => "Trhová cena ({$symbol})",
                    'data' => $priceData,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => "Nákupný priemer: " . number_format($avgPrice, 2, '.', ' ') . " {$symbol}",
                    'data' => $avgPriceLine,
                    'borderColor' => '#3b82f6',
                    'borderDash' => [5, 5],
                    'pointRadius' => 0,
                    'fill' => false,
                ]
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
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => false,
                ],
            ],
        ];
    }
}