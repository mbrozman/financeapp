<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use App\Models\InvestmentPriceHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;
use Brick\Math\BigDecimal; // PRIDANÉ

class IndividualInvestmentChart extends ChartWidget
{
    protected static ?string $heading = 'Vývoj trhovej ceny';
    protected static ?string $maxHeight = '300px';

    public ?Model $record = null;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        if (!$this->record) return ['datasets' => [], 'labels' => []];

        // 1. ZÍSKANIE HISTÓRIE CIEN
        $history = InvestmentPriceHistory::where('investment_id', $this->record->id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        $prices = $history->pluck('price')->toArray();
        $labels = $history->pluck('recorded_at')->map(fn($date) => $date->format('d.M'))->toArray();

        // 2. PRESNÁ NÁKUPNÁ CENA (BigDecimal)
        // Atribút z modelu nám už vracia string
        $avgPriceString = $this->record->average_buy_price_base ?? '0';
        $avgPriceBD = BigDecimal::of($avgPriceString);

        // 3. VYTVORENIE VODOROVNEJ ČIARY (Break-even)
        // Pre graf musíme použiť float, ale vytvoríme ho z presného BigDecimalu
        $averageLineValue = $avgPriceBD->toFloat();
        $averageLineData = count($prices) > 0
            ? array_fill(0, count($prices), $averageLineValue)
            : [];

        $symbol = $this->record->currency?->symbol ?? '$';

        return [
            'datasets' => [
                [
                    'label' => "Trhová cena ({$symbol})",
                    'data' => $prices,
                    'borderColor' => '#10b981',
                    'fill' => false,
                    'pointRadius' => 0,
                    'tension' => 0.4,
                ],
                [
                    // V popise grafu ukážeme pekne sformátovanú sumu
                    'label' => "Nákupný priemer: " . number_format($averageLineValue, 2, ',', ' ') . " {$symbol}",
                    'data' => $averageLineData,
                    'borderColor' => '#3b82f6',
                    'borderDash' => [5, 5], 
                    'pointRadius' => 0,
                    'fill' => false,
                    'stepped' => true, // Zabezpečí dokonale vodorovnú čiaru
                ],
            ],
            'labels' => $labels,
        ];
    }

    public static function getEmptyStateHeading(): ?string
    {
        return 'Historické dáta pre tento symbol ešte nie sú k dispozícii.';
    }

    protected function getType(): string
    {
        return 'line';
    }
}