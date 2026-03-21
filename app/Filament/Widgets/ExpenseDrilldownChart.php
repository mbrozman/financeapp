<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;

class ExpenseDrilldownChart extends Widget
{
    protected static string $view = 'filament.widgets.expense-drilldown-chart';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected static ?int $sort = 3;

    public string $period = '1'; // mesiacov späť
    public ?int $drillCategoryId = null;
    public ?string $drillCategoryName = null;
    public ?string $drillCategoryColor = null;

    public static function canView(): bool
    {
        return !auth()->user()?->isSuperAdmin();
    }

    public function getPeriodOptions(): array
    {
        return [
            '1'  => 'Tento mesiac',
            '3'  => 'Posledné 3 mesiace',
            '6'  => 'Posledných 6 mesiacov',
            '12' => 'Posledný rok',
        ];
    }

    public function drillDown(int $categoryId, string $name, string $color): void
    {
        $this->drillCategoryId   = $categoryId;
        $this->drillCategoryName = $name;
        $this->drillCategoryColor = $color;
    }

    public function drillUp(): void
    {
        $this->drillCategoryId   = null;
        $this->drillCategoryName = null;
        $this->drillCategoryColor = null;
    }

    protected function buildBaseQuery()
    {
        $userId = Auth::id();
        $months = (int) $this->period;
        $from   = now()->subMonths($months)->startOfMonth();

        return Transaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->where('transaction_date', '>=', $from)
            ->whereNotNull('category_id');
    }

    public function getMainData(): array
    {
        $allExpenses = $this->buildBaseQuery()->with('category.parent')->get();

        $grouped = [];
        foreach ($allExpenses as $t) {
            $cat = $t->category;
            if (!$cat) {
                continue;
            }
            $root = $cat->parent_id ? $cat->parent : $cat;
            if (!$root) {
                continue;
            }
            $rootId = $root->id;

            if (!isset($grouped[$rootId])) {
                $grouped[$rootId] = [
                    'total' => 0,
                    'cat'   => $root,
                ];
            }
            $grouped[$rootId]['total'] += abs((float) $t->amount);
        }

        $sorted = collect($grouped)->sortByDesc('total');

        $labels = [];
        $values = [];
        $colors = [];
        $meta   = [];

        foreach ($sorted as $rootId => $data) {
            if ($data['total'] <= 0) {
                continue;
            }
            $cat = $data['cat'];
            $color = $cat->effective_color ?? $cat->color ?? '#94a3b8';
            $labels[] = $cat->name;
            $values[] = round($data['total'], 2);
            $colors[] = $color;
            $meta[] = [
                'id' => $rootId,
                'name' => $cat->name,
                'color' => $color,
            ];
        }

        return compact('labels', 'values', 'colors', 'meta');
    }

    public function getSubData(int $categoryId): array
    {
        $subIds = Category::where('parent_id', $categoryId)->pluck('id')->toArray();
        $ids = array_merge([$categoryId], $subIds);

        $rows = $this->buildBaseQuery()
            ->whereIn('category_id', $ids)
            ->with('category')
            ->get()
            ->groupBy(function ($t) use ($subIds, $categoryId) {
                return in_array($t->category_id, $subIds, true)
                    ? $t->category_id
                    : $categoryId;
            })
            ->map(fn ($group, $catId) => [
                'category_id' => $catId,
                'total' => $group->sum(fn ($t) => abs((float) $t->amount)),
            ]);

        $allCatIds = $rows->keys()->toArray();
        $catMeta = Category::whereIn('id', $allCatIds)->get()->keyBy('id');

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($rows->sortByDesc('total') as $row) {
            $cat = $catMeta->get($row['category_id']);
            if (!$cat || $row['total'] <= 0) {
                continue;
            }
            $color = $cat->effective_color ?? $cat->color ?? $this->drillCategoryColor ?? '#94a3b8';
            $labels[] = $cat->id === $categoryId ? 'Bez podkategórie' : $cat->name;
            $values[] = round($row['total'], 2);
            $colors[] = $color;
        }

        return compact('labels', 'values', 'colors');
    }

    public function getChartData(): array
    {
        $userId = Auth::id();
        $months = (int) $this->period;
        $from   = now()->subMonths($months)->startOfMonth();

        // Ziskame vsetky transakcie so vsetkymi kategoriami naraz
        $allExpenses = Transaction::query()
            ->where('user_id', $userId)
            ->where('type', 'expense')
            ->where('transaction_date', '>=', $from)
            ->whereNotNull('category_id')
            ->with(['category', 'category.parent'])
            ->get();

        // 1. Zoskupime vydavky podla hlavnych kategorii najprv
        $parentGrouped = [];

        foreach ($allExpenses as $t) {
            $cat = $t->category;
            if (!$cat) continue;

            $isParent = $cat->parent_id === null;
            $root = $isParent ? $cat : $cat->parent;
            
            if (!$root) continue;
            
            $rootId = $root->id;
            $amount = abs((float) $t->amount);

            // Hlavna kategoria sum
            if (!isset($parentGrouped[$rootId])) {
                $parentGrouped[$rootId] = [
                    'total' => 0,
                    'cat' => $root,
                    'subs' => [],
                ];
            }
            $parentGrouped[$rootId]['total'] += $amount;

            // Podkategoria sum
            $subId = $isParent ? 'root_' . $rootId : $cat->id;
            if (!isset($parentGrouped[$rootId]['subs'][$subId])) {
                $parentGrouped[$rootId]['subs'][$subId] = [
                    'total' => 0,
                    'cat' => $isParent ? null : $cat, // Null znamena "Ostatne/Hlavna kategoria"
                ];
            }
            $parentGrouped[$rootId]['subs'][$subId]['total'] += $amount;
        }

        // Zoradime podla celkovej sumy hlavnych kategorii (aby najvacsie kategorie isli prve)
        $sortedParents = collect($parentGrouped)->sortByDesc('total');

        $labels = [];
        $values = [];
        $colors = [];
        
        // Pomocne pole pre legendu (hlavne kategorie)
        $legend = [];

        foreach ($sortedParents as $rootId => $parentData) {
            if ($parentData['total'] <= 0) continue;

            $rootCat = $parentData['cat'];
            $rootColor = $rootCat->effective_color ?? $rootCat->color ?? '#94a3b8';

            $legend[] = [
                'name' => $rootCat->name,
                'color' => $rootColor,
                'total' => $parentData['total']
            ];

            // Zoradime podkategorie ramci jednej hlavnej kategorie podla sumy
            $sortedSubs = collect($parentData['subs'])->sortByDesc('total');
            
            // Kolkym farbam v ramci jednej skupiny budeme musiet zmenit odtien?
            $subCount = $sortedSubs->count();
            $step = $subCount > 1 ? (80 / $subCount) : 0; // Rozptyl odtiena z povodnej farby (-40 do 40)
            
            $i = 0;
            foreach ($sortedSubs as $subId => $subData) {
                if ($subData['total'] <= 0) continue;
                
                $subCat = $subData['cat'];
                
                if ($subCat) {
                    $labels[] = $rootCat->name . ' - ' . $subCat->name;
                    $color = $subCat->effective_color;
                } else {
                    $labels[] = $rootCat->name . ' (všeobecne)';
                    // Pre "všeobecné" vytvoríme špecifický odtieň (napr. stredne svetlý index 2)
                    $color = \App\Models\Category::generateHSLShade($rootColor, 2);
                }

                $values[] = round($subData['total'], 2);
                $colors[] = $color;
                $i++;
            }
        }

        return [
            'chart' => [
                'labels' => $labels,
                'values' => $values,
                'colors' => $colors,
            ],
            'legend' => $legend,
        ];
    }

    /**
     * Stmavi alebo zosvetli HEX farbu.
     */
    protected function adjustBrightness($hex, $steps) {
        $hex = str_replace('#', '', $hex);
        if (strlen($hex) == 3) {
            $hex = str_repeat(substr($hex,0,1), 2).str_repeat(substr($hex,1,1), 2).str_repeat(substr($hex,2,1), 2);
        }
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));

        $r = max(0, min(255, $r + $steps));
        $g = max(0, min(255, $g + $steps));
        $b = max(0, min(255, $b + $steps));

        $r_hex = str_pad(dechex($r), 2, '0', STR_PAD_LEFT);
        $g_hex = str_pad(dechex($g), 2, '0', STR_PAD_LEFT);
        $b_hex = str_pad(dechex($b), 2, '0', STR_PAD_LEFT);

        return '#' . $r_hex . $g_hex . $b_hex;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $data = $this->getChartData();
        $activeValues = $this->drillCategoryId ? ($data['sub']['values'] ?? []) : ($data['main']['values'] ?? []);
        $total = array_sum($activeValues);

        return view('filament.widgets.expense-drilldown-chart', [
            'chartData'   => $data,
            'total'       => $total,
            'periodLabel' => $this->getPeriodOptions()[$this->period],
            'periodOptions' => $this->getPeriodOptions(),
        ]);
    }
}
