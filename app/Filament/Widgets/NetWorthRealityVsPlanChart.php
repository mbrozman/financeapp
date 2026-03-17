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
        // Zjednodušený prístup pre lepšiu kompatibilitu
        $snapshots = PortfolioSnapshot::where('user_id', $userId)
            ->whereIn('id', function ($query) use ($userId) {
                $query->select(DB::raw('MAX(id)'))
                    ->from('net_worth_snapshots')
                    ->where('user_id', $userId)
                    ->groupBy(DB::raw("date_trunc('month', recorded_at)"));
            })
            ->orderBy('recorded_at', 'asc')
            ->get();

        if (!$plan) {
            return ['datasets' => [], 'labels' => []];
        }

        $labels = [];
        $realityValues = [];
        $modelValues = [];

        // --- INICIALIZÁCIA ---
        $firstSnapshot = $snapshots->first();
        
        // Ak nemáme žiadne snapshoty, začneme od 0 pre model aspoň na aktuálny mesiac
        $currentModelValue = $firstSnapshot 
            ? BigDecimal::of($firstSnapshot->total_market_value_eur)
            : BigDecimal::zero();
        
        // Koľko EUR mesačne máš podľa plánu ušetriť
        $monthlySavingsIdeal = $plan->getMonthlySavingsAmount(); 
        
        // Nastavenie mesačného úroku (8% / 12 mesiacov)
        $monthlyInterestRate = ($filter === 'with_roi') ? (8 / 100) / 12 : 0;

        // Ak nemáme snapshoty, zobrazíme aspoň projekciu na 12 mesiacov
        if ($snapshots->isEmpty()) {
            $date = now()->startOfYear();
            for ($i = 0; $i < 12; $i++) {
                $labels[] = $date->format('M Y');
                $realityValues[] = null;
                $currentModelValue = $currentModelValue->plus($monthlySavingsIdeal);
                if ($monthlyInterestRate > 0) {
                    $interest = $currentModelValue->multipliedBy($monthlyInterestRate);
                    $currentModelValue = $currentModelValue->plus($interest);
                }
                $modelValues[] = round($currentModelValue->toFloat(), 2);
                $date->addMonth();
            }
        } else {
            foreach ($snapshots as $snapshot) {
                $labels[] = $snapshot->recorded_at->format('M Y');
                $realityValues[] = (float) $snapshot->total_market_value_eur;

                if ($snapshot->id === $firstSnapshot->id) {
                    $modelValues[] = $currentModelValue->toFloat();
                    continue;
                }

                $currentModelValue = $currentModelValue->plus($monthlySavingsIdeal);
                if ($monthlyInterestRate > 0) {
                    $interest = $currentModelValue->multipliedBy($monthlyInterestRate);
                    $currentModelValue = $currentModelValue->plus($interest);
                }
                $modelValues[] = round($currentModelValue->toFloat(), 2);
            }
        }

        // Určenie farby podľa posledného mesiaca (ak je realita dostupná)
        $lastReality = end($realityValues);
        $lastModel = end($modelValues);
        $isAhead = ($lastReality !== null) ? $lastReality >= $lastModel : true;

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