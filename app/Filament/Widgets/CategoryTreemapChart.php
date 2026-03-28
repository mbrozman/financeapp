<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;

class CategoryTreemapChart extends Widget
{
    protected static string $view = 'filament.widgets.category-treemap-chart';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?int $sort = 3;

    public ?string $selectedPeriod = null;

    public static function canView(): bool
    {
        return !auth()->user()?->isSuperAdmin();
    }

    public function getPeriodOptions(): array
    {
        $options = [];
        $start = now()->startOfMonth();
        
        // Generate last 24 months
        for ($i = 0; $i < 24; $i++) {
            $date = $start->copy()->subMonths($i);
            $key = $date->format('Y-m');
            $label = $date->translatedFormat('F Y'); // e.g. "Marec 2026"
            $options[$key] = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }
        
        return $options;
    }

    public function mount()
    {
        $this->selectedPeriod = now()->format('Y-m');
    }

    protected function buildBaseQuery()
    {
        $userId = Auth::id();
        
        $period = $this->selectedPeriod ?? now()->format('Y-m');
        [$year, $month] = explode('-', $period);
        
        $from = now()->setDate((int)$year, (int)$month, 1)->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return Transaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$from, $to])
            ->whereNotNull('category_id');
    }

    public function getChartData(): array
    {
        $userId = Auth::id();
        $allExpenses = $this->buildBaseQuery()
            ->with(['category.planItem', 'category.parent.planItem'])
            ->get();

        $tree = [];
        $total = 0;

        foreach ($allExpenses as $t) {
            $cat = $t->category;
            if (!$cat) continue;

            $pillar = $cat->planItem ?? $cat->parent?->planItem;
            if (!$pillar) continue;

            $amount = abs((float) $t->amount);
            $total += $amount;

            $pName = $pillar->name;
            $pColor = $pillar->color ?? '#94a3b8';

            // Ensure Pillar level
            if (!isset($tree[$pName])) {
                $tree[$pName] = [
                    'amount' => 0,
                    'color' => $pColor,
                    'categories' => []
                ];
            }
            $tree[$pName]['amount'] += $amount;

            // Determine if $cat is a parent or subcategory
            $isParent = $cat->parent_id === null;
            $parentCat = $isParent ? $cat : $cat->parent;
            $subCat = $isParent ? null : $cat;

            $parentName = $parentCat->name;
            $parentColor = $parentCat->effective_color ?? $parentCat->color ?? $pColor;

            // Ensure Category level (Lv1)
            if (!isset($tree[$pName]['categories'][$parentName])) {
                $tree[$pName]['categories'][$parentName] = [
                    'amount' => 0,
                    'color' => $parentColor,
                    'subcategories' => []
                ];
            }
            $tree[$pName]['categories'][$parentName]['amount'] += $amount;

            // Subcategory level (Lv2)
            if ($subCat) {
                $subName = $subCat->name;
                $subColor = $subCat->effective_color ?? $subCat->color ?? $parentColor;

                if (!isset($tree[$pName]['categories'][$parentName]['subcategories'][$subName])) {
                    $tree[$pName]['categories'][$parentName]['subcategories'][$subName] = [
                        'amount' => 0,
                        'color' => $subColor
                    ];
                }
                $tree[$pName]['categories'][$parentName]['subcategories'][$subName]['amount'] += $amount;
            } else {
                // If it's a direct transaction to parent, add to a "General" subcategory for that parent
                $generalName = "Všeobecné (" . $parentName . ")";
                if (!isset($tree[$pName]['categories'][$parentName]['subcategories'][$generalName])) {
                    $tree[$pName]['categories'][$parentName]['subcategories'][$generalName] = [
                        'amount' => 0,
                        'color' => $parentColor
                    ];
                }
                $tree[$pName]['categories'][$parentName]['subcategories'][$generalName]['amount'] += $amount;
            }
        }

        // Formátujeme pre ApexCharts Multi-Series:
        // Séria = Hlavná kategória (Parent)
        // Data points = Podkategórie (Subcategory)
        
        $series = [];
        $colors = [];
        $parentColors = [];
        
        // Zoskupíme si najprv podľa Hlavnej kategórie
        $groupedByCategory = [];
        
        foreach ($tree as $pName => $pData) {
            foreach ($pData['categories'] as $cName => $cData) {
                if (!isset($groupedByCategory[$cName])) {
                    $groupedByCategory[$cName] = [
                        'color' => $cData['color'] ?? $pData['color'] ?? '#94a3b8',
                        'subcategories' => []
                    ];
                }
                
                foreach ($cData['subcategories'] as $subName => $subData) {
                    if (!isset($groupedByCategory[$cName]['subcategories'][$subName])) {
                        $groupedByCategory[$cName]['subcategories'][$subName] = [
                            'amount' => 0,
                            'color' => $subData['color'] ?? $cData['color']
                        ];
                    }
                    $groupedByCategory[$cName]['subcategories'][$subName]['amount'] += $subData['amount'];
                }
            }
        }
        
        // Vytvoríme série
        foreach ($groupedByCategory as $cName => $cData) {
            $seriesData = [];
            foreach ($cData['subcategories'] as $subName => $subInfo) {
                $seriesData[] = [
                    'x' => $subName,
                    'y' => round($subInfo['amount'], 2),
                    'fillColor' => $subInfo['color']
                ];
            }
            
            if (!empty($seriesData)) {
                // Utriediť data blocky od najvyššieho po najnižšie
                usort($seriesData, fn($a, $b) => $b['y'] <=> $a['y']);
                
                $series[] = [
                    'name' => $cName,
                    'data' => $seriesData,
                    'mappedColor' => $cData['color'] // DOČASNE ULOŽÍME DO POĽA
                ];
                $parentColors[$cName] = $cData['color'];
            }
        }

        // Sort series by total amount (descending) tak aby najväčšie hlavné kategórie boli prvé
        usort($series, fn($a, $b) => array_sum(array_column($b['data'], 'y')) <=> array_sum(array_column($a['data'], 'y')));

        // AŽ PO SORTINGU VYTIAHNEME FARBY, ABY OSTALI SYNCHRONIZOVANÉ SO SÉRIAMI
        $colors = [];
        foreach ($series as &$s) {
            $colors[] = $s['mappedColor'];
            unset($s['mappedColor']); // Clean up pre Vue/Alpine
        }

        return [
            'total' => $total,
            'series' => $series,
            'colors' => $colors,
            'parent_colors' => $parentColors
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'chartData' => $this->getChartData(),
            'periodLabel' => $this->getPeriodOptions()[$this->selectedPeriod] ?? '',
            'periodOptions' => $this->getPeriodOptions(),
        ]);
    }
}
