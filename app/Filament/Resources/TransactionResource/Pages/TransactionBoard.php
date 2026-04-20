<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Filament\Resources\Pages\Page;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Livewire\Attributes\Url;
use Livewire\Attributes\On;

class TransactionBoard extends Page implements Forms\Contracts\HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = TransactionResource::class;

    protected static string $view = 'filament.resources.transaction-resource.pages.transaction-board';

    protected static ?string $title = 'Board Transakcií';

    public ?string $month = null;
    public ?string $smartInput = '';
    public ?array $quickAdd = [];

    // Pre modal s detailom podkategórie
    public bool $showDetailModal = false;
    public string $detailTitle = '';
    public array $detailTransactions = [];

    public function mount(): void
    {
        if (!$this->month) {
            $this->month = now()->format('Y-m');
        }
    }

    public function getBoardSectionsProperty(): Collection
    {
        $date = Carbon::parse($this->month . '-01');
        $sections = collect();

        // 1. VÝDAVKY (Hlavné kategórie)
        $mainCategories = Category::where('user_id', auth()->id())
            ->whereNull('parent_id')
            ->where('type', 'expense')
            ->with('children')
            ->get();

        $allTransactions = Transaction::where('user_id', auth()->id())
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->with(['category.parent', 'account.currency'])
            ->get();

        foreach ($mainCategories as $category) {
            $childIds = $category->children->pluck('id')->toArray();
            $childIds[] = $category->id;
            
            $trans = $allTransactions->whereIn('category_id', $childIds)->where('type', 'expense');
            
            $sections->push([
                'id' => 'cat-' . $category->id,
                'model_id' => $category->id,
                'title' => $category->name,
                'type' => 'expense_category',
                'sum' => abs($trans->sum('amount')),
                'transactions' => $trans,
                'children' => $category->children,
                'color' => $category->effective_color,
                'sort' => abs($trans->sum('amount')) > 0 ? 100 : 0,
            ]);
        }

        // 2. PRÍJMY
        $incomes = $allTransactions->where('type', 'income');
        // Pre príjmy potrebujeme podkategórie pre Quick Add
        $incomeCategories = Category::where('user_id', auth()->id())
            ->where('type', 'income')
            ->whereNotNull('parent_id')
            ->get();

        $sections->push([
            'id' => 'incomes',
            'title' => 'PRÍJMY',
            'type' => 'income_section',
            'sum' => $incomes->sum('amount'),
            'transactions' => $incomes,
            'children' => $incomeCategories,
            'color' => '#22c55e',
            'sort' => 1000,
        ]);

        // 3. INTERNÉ PREVODY
        $transfers = $allTransactions->where('type', 'transfer');
        if ($transfers->isNotEmpty()) {
            $sections->push([
                'id' => 'transfers',
                'title' => 'INTERNÉ PREVODY',
                'type' => 'special',
                'sum' => abs($transfers->where('amount', '<', 0)->sum('amount')),
                'transactions' => $transfers,
                'color' => '#232323',
                'sort' => 900,
            ]);
        }

        // 4. PRAVIDELNÉ PLATBY
        $recurring = $allTransactions->filter(function($t) {
            return str_contains($t->description, 'Pravidelný') || str_contains($t->description, 'Automatická');
        });
        if ($recurring->isNotEmpty()) {
            $sections->push([
                'id' => 'recurring',
                'title' => 'PRAVIDELNÉ PLATBY',
                'type' => 'special',
                'sum' => abs($recurring->sum('amount')),
                'transactions' => $recurring,
                'color' => '#3b82f6',
                'sort' => 800,
            ]);
        }

        // 5. INVESTÍCIE
        $investments = $allTransactions->filter(function($t) {
            return str_contains($t->description, 'Investičný');
        });
        if ($investments->isNotEmpty()) {
            $sections->push([
                'id' => 'investments',
                'title' => 'INVESTÍCIE',
                'type' => 'special',
                'sum' => abs($investments->sum('amount')),
                'transactions' => $investments,
                'color' => '#f97316',
                'sort' => 700,
            ]);
        }

        // 6. NEZARADENÉ (Bez kategórie)
        $uncategorized = $allTransactions->whereNull('category_id');
        if ($uncategorized->isNotEmpty()) {
            $sections->push([
                'id' => 'uncategorized',
                'title' => 'NEZARADENÉ',
                'type' => 'special',
                'sum' => abs($uncategorized->sum('amount')),
                'transactions' => $uncategorized,
                'color' => '#6b7280', // Gray-500
                'sort' => 2000, // Na koniec
            ]);
        }

        // ZORADENIE A FILTROVANIE: Vlastné zo settings
        $boardSettings = auth()->user()->settings['board_settings'] ?? [];
        $order = collect($boardSettings)->pluck('id')->toArray();
        $hiddenIds = collect($boardSettings)->where('is_visible', false)->pluck('id')->toArray();

        // Odstránime schované
        $sections = $sections->reject(fn($s) => in_array($s['id'], $hiddenIds));
        
        $sorted = $sections->sort(function ($a, $b) use ($order) {
            if (!empty($order)) {
                $posA = array_search($a['id'], $order);
                $posB = array_search($b['id'], $order);
                
                if ($posA !== false && $posB !== false) return $posA <=> $posB;
                if ($posA !== false) return -1;
                if ($posB !== false) return 1;
            }

            // Fallback na pôvodné priority
            $priorities = ['incomes' => 0, 'transfers' => 1];
            $pA = $priorities[$a['id']] ?? 10;
            $pB = $priorities[$b['id']] ?? 10;

            if ($pA !== $pB) return $pA <=> $pB;
            return $b['sum'] <=> $a['sum'];
        });

        // PREPOČET PRE MASONRY (Columns) aby šlo zľava doprava
        return $this->reorderForMasonry($sorted, 4);
    }

    /**
     * Prepočíta poradie prvkov pre CSS Columns, aby sa vizuálne radili zľava doprava.
     */
    protected function reorderForMasonry(Collection $collection, int $columns): Collection
    {
        $items = $collection->values();
        $total = $items->count();
        if ($total === 0) return $collection;

        $rows = ceil($total / $columns);
        $reordered = collect();

        for ($r = 0; $r < $rows; $r++) {
            for ($c = 0; $c < $columns; $c++) {
                $index = $r + ($c * $rows);
                if ($items->has($index)) {
                    $reordered->push($items[$index]);
                }
            }
        }

        // Musíme to vrátiť v poradí, v akom to Columns vykresľuje (stĺpec po stĺpci)
        // Ale Columns očakáva 1, 2, 3, 4 v prvom stĺpci... počkať.
        // Ak chceme 1 2 3 4 v prvom riadku, tak v poli musia byť:
        // C1: 1, 5, 9
        // C2: 2, 6, 10...
        // Pole: 1, 5, 9, 2, 6, 10, 3, 7, 4, 8.

        $final = collect();
        for ($c = 0; $c < $columns; $c++) {
            for ($r = 0; $r < $rows; $r++) {
                $itemIndex = ($r * $columns) + $c;
                if ($items->has($itemIndex)) {
                    $final->push($items[$itemIndex]);
                }
            }
        }

        return $final;
    }

    public function saveQuickTransaction(string $sectionId, int $categoryId = null): void
    {
        $id = $categoryId ?? $sectionId;
        $data = $this->quickAdd[$id] ?? null;

        if (!$data || !isset($data['amount']) || empty($data['amount'])) {
            return;
        }

        $this->executeTransaction(
            amountStr: $data['amount'],
            categoryId: !empty($data['sub_category_id']) ? $data['sub_category_id'] : null,
            type: $sectionId === 'incomes' ? 'income' : 'expense'
        );

        $this->quickAdd[$id] = [];
        Notification::make()->title('Transakcia pridaná')->success()->send();
        $this->dispatch('refresh-board');
    }

    /**
     * Inteligentné spracovanie textového vstupu (napr. "15.50 obed")
     */
    public function saveSmartTransaction(): void
    {
        if (empty($this->smartInput)) return;

        // 1. REGEX pre rozdelenie na sumu a popis/kategóriu
        // Podporujeme: "20.50 obed" alebo "obed 20.50"
        $input = trim($this->smartInput);
        
        // Hľadáme číslo na začiatku alebo na konci
        $amountExpr = '';
        $searchText = '';

        if (preg_match('/^([0-9\+\-\*\/\,\.\(\)\s]+)(.*)$/', $input, $matches)) {
            $amountExpr = trim($matches[1]);
            $searchText = trim($matches[2]);
        } elseif (preg_match('/^(.*)\s+([0-9\+\-\*\/\,\.\(\)\s]+)$/', $input, $matches)) {
            $searchText = trim($matches[1]);
            $amountExpr = trim($matches[2]);
        }

        if (empty($amountExpr)) {
            Notification::make()->title('Suma nenájdená')->body('Zadajte sumu, napr. "15 obed"')->danger()->send();
            return;
        }

        // 2. VYHĽADANIE KATEGÓRIE (Case-insensitive)
        $categoryId = null;
        $type = 'expense';

        if (!empty($searchText)) {
            $term = mb_strtolower($searchText);
            
            // Hľadáme v podkategóriách
            $category = Category::where('user_id', auth()->id())
                ->whereNotNull('parent_id')
                ->where(DB::raw('LOWER(name)'), 'LIKE', '%' . $term . '%')
                ->first();

            if (!$category) {
                // Skúsime hlavné kategórie
                $category = Category::where('user_id', auth()->id())
                    ->whereNull('parent_id')
                    ->where(DB::raw('LOWER(name)'), 'LIKE', '%' . $term . '%')
                    ->first();
            }

            if ($category) {
                $categoryId = $category->id;
                $type = $category->type instanceof \App\Enums\TransactionType ? $category->type->value : (string)$category->type;
            }
        }

        // 3. VYKONANIE
        $this->executeTransaction($amountExpr, $categoryId, $type, $searchText ?: 'Rýchly zápis');

        if (!$categoryId && !empty($searchText)) {
            Notification::make()
                ->title('Kategória nenájdená')
                ->body("Zápis '$searchText' bol uložený ako 'Nezaradené'.")
                ->warning()
                ->send();
        } else {
            Notification::make()->title('Transakcia zaznamenaná')->success()->send();
        }

        $this->smartInput = '';
        $this->dispatch('refresh-board');
    }

    /**
     * Spoločné jadro pre uloženie transakcie
     */
    protected function executeTransaction(string $amountStr, ?int $categoryId, string $type, string $description = 'Rýchly zápis'): void
    {
        $amount = $this->evaluateMath($amountStr);
        if ($amount === 0.0) return;

        $account = Account::where('user_id', auth()->id())->first();
        if (!$account) {
            Notification::make()->title('Chyba')->body('Nenašiel sa žiaden účet.')->danger()->send();
            return;
        }

        $transaction = new Transaction();
        $transaction->forceFill([
            'user_id'          => auth()->id(),
            'type'             => $type,
            'account_id'       => $account->id,
            'category_id'      => $categoryId,
            'transaction_date' => Carbon::parse($this->month . '-01')->isCurrentMonth()
                ? now()
                : Carbon::parse($this->month . '-01')->endOfMonth(),
            'description'      => $description,
            'amount'           => $amount,
        ]);
        $transaction->save();
    }

    /**
     * Otvorí modal s detailom transakcií pre danú podkategóriu.
     */
    public function showSubcategoryDetail(int $categoryId, string $categoryName): void
    {
        $date = Carbon::parse($this->month . '-01');

        $transactions = Transaction::where('user_id', auth()->id())
            ->where('category_id', $categoryId)
            ->whereMonth('transaction_date', $date->month)
            ->whereYear('transaction_date', $date->year)
            ->orderBy('transaction_date', 'desc')
            ->get()
            ->map(fn($t) => [
                'id'          => $t->id,
                'date'        => $t->transaction_date->format('d.m.Y'),
                'description' => $t->description,
                'amount'      => number_format(abs((float) $t->amount), 2, ',', ' '),
                'type'        => $t->type instanceof \App\Enums\TransactionType ? $t->type->value : $t->type,
            ])
            ->toArray();

        $this->detailTitle = $categoryName;
        $this->detailTransactions = $transactions;
        $this->showDetailModal = true;
    }

    public function closeDetailModal(): void
    {
        $this->showDetailModal = false;
        $this->detailTransactions = [];
        $this->detailTitle = '';
    }

    /**
     * Vyhodnotí matematický výraz v reťazci (napr. "10+5").
     */
    protected function evaluateMath($expression): float
    {
        if (is_numeric($expression)) return (float) $expression;

        $expression = str_replace(',', '.', (string)$expression);
        $expression = preg_replace('/[^0-9\+\-\*\/\.\(\)]/', '', $expression);

        if (empty($expression)) return 0;

        try {
            // Po vyčistení regexom je bezpečné použiť eval pre matematický výraz
            $result = eval('return ' . $expression . ';');
            return (float) $result;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    #[On('filterUpdated')]
    public function updateMonth($month): void
    {
        $this->month = $month;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('board_settings')
                ->label('')
                ->icon('heroicon-m-cog-6-tooth')
                ->color('gray')
                ->modalHeading('Nastavenia Boardu')
                ->modalDescription('Zmeňte poradie a viditeľnosť stĺpcov.')
                ->form([
                    Forms\Components\Repeater::make('board_settings')
                        ->label('Zoznam stĺpcov')
                        ->schema([
                            Forms\Components\Grid::make(12)
                                ->schema([
                                    Forms\Components\TextInput::make('title')
                                        ->disabled()
                                        ->label('Názov stĺpca')
                                        ->columnSpan(8),
                                    Forms\Components\Toggle::make('is_visible')
                                        ->label('Viditeľný')
                                        ->onIcon('heroicon-m-eye')
                                        ->offIcon('heroicon-m-eye-slash')
                                        ->inline(false)
                                        ->columnSpan(4),
                                    Forms\Components\Hidden::make('id'),
                                ]),
                        ])
                        ->reorderable()
                        ->addable(false)
                        ->deletable(false)
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null),
                ])
                ->fillForm(function() {
                    // Musíme vziať sekcie v aktuálnom poradí pred prepočtom Masonry
                    $mainCategories = Category::where('user_id', auth()->id())
                        ->whereNull('parent_id')
                        ->where('type', 'expense')
                        ->get();
                        
                    $sections = $mainCategories->map(fn($c) => ['id' => 'cat-'.$c->id, 'title' => $c->name])
                        ->push(['id' => 'incomes', 'title' => 'PRÍJMY'])
                        ->push(['id' => 'transfers', 'title' => 'INTERNÉ PREVODY'])
                        ->push(['id' => 'recurring', 'title' => 'PRAVIDELNÉ PLATBY'])
                        ->push(['id' => 'investments', 'title' => 'INVESTÍCIE'])
                        ->push(['id' => 'uncategorized', 'title' => 'NEZARADENÉ']);

                    $savedSettings = auth()->user()->settings['board_settings'] ?? [];
                    
                    $sorted = $sections->map(function($s) use ($savedSettings) {
                        $setting = collect($savedSettings)->firstWhere('id', $s['id']);
                        return [
                            'id' => $s['id'],
                            'title' => $s['title'],
                            'is_visible' => $setting['is_visible'] ?? true,
                        ];
                    })->sort(function($a, $b) use ($savedSettings) {
                        if (!empty($savedSettings)) {
                            $order = collect($savedSettings)->pluck('id')->toArray();
                            $posA = array_search($a['id'], $order);
                            $posB = array_search($b['id'], $order);
                            if ($posA !== false && $posB !== false) return $posA <=> $posB;
                            if ($posA !== false) return -1;
                            if ($posB !== false) return 1;
                        }
                        return 0;
                    });

                    return [
                        'board_settings' => $sorted->values()->toArray()
                    ];
                })
                ->action(function (array $data) {
                    $settings = auth()->user()->settings ?? [];
                    $settings['board_settings'] = $data['board_settings'];
                    
                    auth()->user()->update(['settings' => $settings]);
                    
                    Notification::make()->title('Nastavenia uložené')->success()->send();
                }),

            Actions\Action::make('list_view')
                ->label('Zobraziť zoznam')
                ->color('gray')
                ->icon('heroicon-m-list-bullet')
                ->url(static::$resource::getUrl('list')),

            Actions\Action::make('create')
                ->label('Vytvoriť')
                ->icon('heroicon-m-plus')
                ->color('primary')
                ->url(static::$resource::getUrl('create')),

            Actions\Action::make('transfer')
                ->label('Interný prevod')
                ->icon('heroicon-m-arrows-right-left')
                ->color('info')
                ->form([
                    Forms\Components\Select::make('from_account_id')
                        ->label('Z účtu')
                        ->options(Account::where('user_id', auth()->id())->pluck('name', 'id'))
                        ->required(),

                    Forms\Components\Select::make('to_account_id')
                        ->label('Na účet')
                        ->options(Account::where('user_id', auth()->id())->pluck('name', 'id'))
                        ->required()
                        ->different('from_account_id'),

                    Forms\Components\Grid::make(2)
                        ->schema([
                            Forms\Components\Select::make('parent_category_id')
                                ->label('Hlavná skupina (nepovinné)')
                                ->options(Category::where('user_id', auth()->id())
                                    ->whereNull('parent_id')
                                    ->where('type', 'expense')
                                    ->pluck('name', 'id')
                                )
                                ->live()
                                ->searchable(),

                            Forms\Components\Select::make('category_id')
                                ->label('Podkategória / Detail')
                                ->options(function (Forms\Get $get) {
                                    $parentId = $get('parent_category_id');
                                    $query = Category::where('user_id', auth()->id())
                                        ->whereNotNull('parent_id')
                                        ->where('type', 'expense');
                                        
                                    if ($parentId) {
                                        $query->where('parent_id', $parentId);
                                    }
                                    
                                    return $query->with('parent')
                                        ->get()
                                        ->groupBy('parent.name')
                                        ->map(fn($categories) => $categories->pluck('name', 'id'))
                                        ->toArray();
                                })
                                ->searchable(),
                        ]),

                    Forms\Components\TextInput::make('amount')
                        ->label('Suma')
                        ->numeric()
                        ->required()
                        ->minValue(0.01),

                    Forms\Components\DatePicker::make('transaction_date')
                        ->label('Dátum')
                        ->default(now())
                        ->required(),
                ])
                ->action(function (array $data) {
                    DB::transaction(function () use ($data) {
                        $t1 = Transaction::create([
                            'user_id' => auth()->id(),
                            'account_id' => $data['from_account_id'],
                            'category_id' => $data['category_id'] ?? null,
                            'type' => 'transfer',
                            'amount' => -abs($data['amount']),
                            'transaction_date' => $data['transaction_date'],
                            'description' => 'Interný prevod (Odchod)',
                        ]);

                        $t2 = Transaction::create([
                            'user_id' => auth()->id(),
                            'account_id' => $data['to_account_id'],
                            'linked_transaction_id' => $t1->id,
                            'type' => 'transfer',
                            'amount' => abs($data['amount']),
                            'transaction_date' => $data['transaction_date'],
                            'description' => 'Interný prevod (Príchod)',
                        ]);

                        $t1->update(['linked_transaction_id' => $t2->id]);
                    });
                })
                ->successNotificationTitle('Prevod úspešne prebehol'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TransactionResource\Widgets\TransactionMonthFilter::class,
            TransactionResource\Widgets\TransactionSummaryWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 12;
    }
}
