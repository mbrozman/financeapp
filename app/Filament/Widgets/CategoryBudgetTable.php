<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use App\Models\Transaction;
use App\Services\CurrencyService;
use Illuminate\Support\Facades\Auth;
use Filament\Widgets\Widget;

class CategoryBudgetTable extends Widget
{
    protected static string $view = 'filament.widgets.category-budget-table';

    protected int | string | array $columnSpan = 'full'; // Tabuľka bude na celú šírku pre lepšiu čitateľnosť

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
        
        for ($i = 0; $i < 24; $i++) {
            $date = $start->copy()->subMonths($i);
            $key = $date->format('Y-m');
            $label = $date->translatedFormat('F Y');
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

    public function getBudgetData(): array
    {
        $userId = Auth::id();
        try {
            $allExpenses = $this->buildBaseQuery()
                ->with(['category', 'account'])
                ->get();

            $categoryIds = $allExpenses->pluck('category_id')->filter()->unique();
            $categories = Category::with(['planItem', 'parent.planItem'])->findMany($categoryIds)->keyBy('id');

            $tree = [];
            $totalSpent = 0;

            foreach ($allExpenses as $t) {
                $cat = $categories->get($t->category_id);
                if (!$cat) continue;

                $pillar = $cat->planItem ?? $cat->parent?->planItem;
                $pName = $pillar->name ?? 'Ostatné výdavky';
                $pColor = $pillar->color ?? '#94a3b8';

                $amount = abs((float) $t->amount);
                
                // Bezpečné získanie meny cez account
                $acc = $t->account;
                $currencyId = $acc ? $acc->currency_id : 4;

                if ($currencyId != 4) { 
                    $amount = (float) CurrencyService::convertToEur((string)$amount, $currencyId);
                }
                $totalSpent += $amount;

                if (!isset($tree[$pName])) {
                    $tree[$pName] = [
                        'amount' => 0,
                        'color' => $pColor,
                        'is_savings' => $pillar->contributes_to_net_worth ?? false,
                        'parent_categories' => []
                    ];
                }
                $tree[$pName]['amount'] += $amount;

                $isParent = $cat->parent_id === null;
                $parentCat = $isParent ? $cat : $cat->parent;
                $subCat = $isParent ? null : $cat;

                $parentName = $parentCat->name;
                $parentColor = $parentCat->effective_color ?? $parentCat->color ?? $pColor;
                $parentLimit = (float)($parentCat->monthly_limit ?? 0);

                if (!isset($tree[$pName]['parent_categories'][$parentName])) {
                    $tree[$pName]['parent_categories'][$parentName] = [
                        'amount' => 0,
                        'color' => $parentColor,
                        'limit' => $parentLimit,
                        'subcategories' => []
                    ];
                }
                $tree[$pName]['parent_categories'][$parentName]['amount'] += $amount;

                if ($subCat) {
                    $subName = $subCat->name;
                    $subLimit = (float)($subCat->monthly_limit ?? 0);

                    if (!isset($tree[$pName]['parent_categories'][$parentName]['subcategories'][$subName])) {
                        $tree[$pName]['parent_categories'][$parentName]['subcategories'][$subName] = [
                            'amount' => 0,
                            'limit' => $subLimit
                        ];
                    }
                    $tree[$pName]['parent_categories'][$parentName]['subcategories'][$subName]['amount'] += $amount;
                }
            }

            // Final sorting and percent calculation
            foreach ($tree as $pName => &$pData) {
                uasort($pData['parent_categories'], fn($a, $b) => $b['amount'] <=> $a['amount']);
                foreach ($pData['parent_categories'] as &$cData) {
                    $cData['percent'] = $cData['limit'] > 0 ? ($cData['amount'] / $cData['limit']) * 100 : 0;
                    uasort($cData['subcategories'], fn($a, $b) => $b['amount'] <=> $a['amount']);
                    foreach ($cData['subcategories'] as &$sub) {
                        $sub['percent'] = $sub['limit'] > 0 ? ($sub['amount'] / $sub['limit']) * 100 : 0;
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("BudgetTable Error: " . $e->getMessage());
            return ['total' => $totalSpent ?? 0, 'pillars' => $tree ?? []];
        }

        return [
            'total' => $totalSpent,
            'pillars' => $tree
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view(static::$view, [
            'data' => $this->getBudgetData(),
            'periodLabel' => $this->getPeriodOptions()[$this->selectedPeriod] ?? '',
            'periodOptions' => $this->getPeriodOptions(),
        ]);
    }
}
