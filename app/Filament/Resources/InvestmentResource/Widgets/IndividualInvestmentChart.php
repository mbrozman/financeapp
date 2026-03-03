<?php

namespace App\Filament\Resources\InvestmentResource\Widgets;

use App\Models\InvestmentPriceHistory;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class IndividualInvestmentChart extends ChartWidget
{
    protected static ?string $heading = 'Vývoj trhovej ceny';
    protected static ?string $maxHeight = '300px'; // Trochu ho znížime, aby ladil s kartami

    public ?Model $record = null;

    // TENTO RIADOK JE KĽÚČOVÝ: Hovorí, že graf zaberie len 1 slot v mriežke
    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        if (!$this->record) return ['datasets' => [], 'labels' => []];

        // 1. Získame históriu cien pre čiaru trhu
        $history = \App\Models\InvestmentPriceHistory::where('investment_id', $this->record->id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        $prices = $history->pluck('price')->toArray();
        $labels = $history->pluck('recorded_at')->map(fn($date) => $date->format('d.M'))->toArray();

        // 2. ZÍSKAME NÁKUPNÚ CENU (TUTO BOLA MOŽNO CHYBA V NÁZVE)
        // Voláme atribút, ktorý sme práve definovali v modeli
        $avgPriceBase = (float) $this->record->average_buy_price_base;

        // 3. Vytvoríme vodorovnú čiaru
        $averageLineData = count($prices) > 0
            ? array_fill(0, count($prices), $avgPriceBase)
            : [];

        return [
            'datasets' => [
                [
                    'label' => "Trhová cena",
                    'data' => $prices,
                    'borderColor' => '#10b981',
                    'fill' => false,
                    'pointRadius' => 0,
                ],
                [
                    'label' => "Nákupný priemer: " . number_format($avgPriceBase, 2) . " " . ($this->record->currency?->symbol ?? '$'),
                    'data' => $averageLineData,
                    'borderColor' => '#3b82f6',
                    'borderDash' => [5, 5], // Prerušovaná čiara
                    'pointRadius' => 0,
                    'fill' => false,
                    'stepped' => true,
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
