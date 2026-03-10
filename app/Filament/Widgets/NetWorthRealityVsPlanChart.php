<?php

namespace App\Filament\Widgets;

use App\Models\FinancialPlan;
use App\Models\PortfolioSnapshot;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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
        $filter = $this->filter ?? 'with_roi';
        $userId = Auth::id();
        $plan = FinancialPlan::with('items')->where('user_id', $userId)->first();

        // 1. ZÍSKAME POSLEDNÝ SNAPSHOT KAŽDÉHO MESIACA
        // Tento SQL dotaz vráti len jeden záznam (ten najnovší) pre každý mesiac
        $snapshots = PortfolioSnapshot::where('user_id', $userId)
            ->whereIn('recorded_at', function ($query) {
                $query->select(DB::raw('MAX(recorded_at)'))
                    ->from('net_worth_snapshots')
                    ->groupBy(DB::raw("date_trunc('month', recorded_at)"));
            })
            ->orderBy('recorded_at', 'asc')
            ->get();

        if ($snapshots->count() < 2 || !$plan) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = [];
        $realityValues = [];
        $modelValues = [];

        // --- INICIALIZÁCIA ---
        $firstSnapshot = $snapshots->first();
        $currentModelValue = BigDecimal::of($firstSnapshot->total_market_value_eur);
        
        // Koľko EUR mesačne máš podľa plánu ušetriť (napr. 50% z 2200 = 1100 €)
        $monthlySavingsIdeal = $plan->getMonthlySavingsAmount(); 
        
        // Nastavenie mesačného úroku (8% / 12 mesiacov)
        $monthlyInterestRate = ($filter === 'with_roi') ? (8 / 100) / 12 : 0;

        foreach ($snapshots as $snapshot) {
            $labels[] = $snapshot->recorded_at->format('M Y');
            
            // A. REALITA: Čo si v ten mesiac naozaj mal na účtoch
            $realityValues[] = (float) $snapshot->total_market_value_eur;

            // B. MODEL: Kde si mal byť podľa vzorca
            if ($snapshot->id === $firstSnapshot->id) {
                $modelValues[] = $currentModelValue->toFloat();
                continue;
            }

            // Každý mesiac pripočítame fixný vklad z plánu
            $currentModelValue = $currentModelValue->plus($monthlySavingsIdeal);

            // Ak je zapnutý výnos, zhodnotíme celú sumu (zložené úročenie)
            if ($monthlyInterestRate > 0) {
                $interest = $currentModelValue->multipliedBy($monthlyInterestRate);
                $currentModelValue = $currentModelValue->plus($interest);
            }

            $modelValues[] = round($currentModelValue->toFloat(), 2);
        }

        // Určenie farby podľa posledného mesiaca
        $isAhead = end($realityValues) >= end($modelValues);

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
        return false;
    }
}