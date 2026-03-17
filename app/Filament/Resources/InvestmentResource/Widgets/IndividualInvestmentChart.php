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

        // 1. ZÍSKANIE CIEĽOVEJ MENY
        $currencyCode = request()->query('currency') ?: request()->query('table_currency') ?: $this->record->currency?->code;
        $targetCurrency = \App\Models\Currency::where('code', $currencyCode)->first();
        $symbol = $targetCurrency->symbol ?? $currencyCode;

        // 2. ZÍSKANIE HISTÓRIE CIEN
        $history = InvestmentPriceHistory::where('investment_id', $this->record->id)
            ->orderBy('recorded_at', 'asc')
            ->get();

        $priceData = [];
        foreach ($history as $h) {
            $price = (float) $h->price;
            if ($currencyCode !== $this->record->currency?->code) {
                // Prepočet historickej ceny (používame aktuálny kurz pre porovnateľnosť v čase, 
                // alternatívne by sme potrebovali historickú tabuľku kurzov)
                $price = (float) \App\Services\CurrencyService::convert(
                    (string)$price, 
                    $this->record->currency_id, 
                    $targetCurrency?->id
                );
            }
            $priceData[] = $price;
        }

        $labels = $history->pluck('recorded_at')->map(fn($date) => $date->format('d.M'))->toArray();

        // 3. PRIEMERNÁ NÁKUPKA (Prepočítaná)
        $avgPriceBase = $this->record->average_buy_price_base;
        $avgPriceDisplay = (float)$avgPriceBase;

        if ($currencyCode !== $this->record->currency?->code) {
            // Použijeme modelovú metódu pre EUR alebo convert pre ostatné
            if ($currencyCode === 'EUR') {
                $avgPriceDisplay = (float)$this->record->average_buy_price_eur;
            } else {
                $avgPriceDisplay = (float) \App\Services\CurrencyService::convert(
                    (string)$avgPriceBase, 
                    $this->record->currency_id, 
                    $targetCurrency?->id
                );
            }
        }

        $avgPriceLine = array_fill(0, count($priceData), $avgPriceDisplay);

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
                    'label' => "Nákupný priemer: " . number_format($avgPriceDisplay, 2, '.', ' ') . " {$symbol}",
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