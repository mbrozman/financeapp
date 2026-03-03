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

        // 1. Získame históriu cien
        $history = \App\Models\InvestmentPriceHistory::where('investment_id', $this->record->id)
            ->orderBy('recorded_at', 'asc')
            ->get();
        if ($history->isEmpty()) {
            return [
                'datasets' => [
                    [
                        'label' => 'Dáta sa pripravujú...',
                        'data' => [0, 0, 0], // Ukážeme prázdnu čiaru
                        'borderColor' => '#94a3b8',
                    ],
                ],
                'labels' => ['Sťahujem', 'dáta', 'z burzy...'],
            ];
        }
        $prices = $history->pluck('price')->toArray();
        $labels = $history->pluck('recorded_at')->map(fn($date) => $date->format('d.M'))->toArray();

        // 2. Získame nákupnú cenu v USD
        $avgPriceUsd = (float)$this->record->average_buy_price_usd;

        // 3. Vytvoríme pole, ktoré má v každom bode rovnakú nákupnú cenu
        $averageLineData = array_fill(0, count($prices), $avgPriceUsd);

        return [
            'datasets' => [
                // DATASET 1: TRHOVÁ CENA (Zelená/Červená)
                [
                    'label' => "Trhová cena (USD)",
                    'data' => $prices,
                    'fill' => false,
                    'tension' => 0.4,
                    'borderColor' => '#10b981', // Zelená
                    'pointRadius' => 0, // Čistá čiara bez bodiek
                ],
                // DATASET 2: MOJA NÁKUPNÁ CENA (Modrá prerušovaná čiara)
                [
                    'label' => "Priemerná nákupná cena: " . number_format($avgPriceUsd, 2) . " $",
                    'data' => $averageLineData,
                    'fill' => false,
                    'borderColor' => '#3b82f6', // Krásna modrá
                    'borderDash' => [5, 5],    // PRERUŠOVANÁ ČIARA (5px čiara, 5px medzera)
                    'borderWidth' => 2,
                    'pointRadius' => 0,        // Skryjeme body
                    'stepped' => true,         // Urobí ju dokonale plochú
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
