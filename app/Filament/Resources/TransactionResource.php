<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use App\Models\Category;
use App\Models\FinancialPlanItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\IconColumn;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = '💸 OPERÁCIE';
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string { return 'Transakcie'; }
    public static function getPluralLabel(): string { return 'Transakcie'; }
    public static function getModelLabel(): string { return 'Transakcia'; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detaily platby')
                    ->schema([
                        
                        // 1. SELECT: ÚČET
                        Forms\Components\Select::make('account_id')
                            ->label('Účet')
                            ->relationship('account', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        // 2. SELECT: TYP POHYBU
                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'income' => 'Príjem',
                                'expense' => 'Výdavok',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        // 3. SELECT: HLAVNÁ KATEGÓRIA (RODIČ)
                        Forms\Components\Select::make('parent_category_id')
                            ->label('Hlavná skupina')
                            ->options(fn(\Filament\Forms\Get $get) => Category::whereNull('parent_id')
                                ->where('type', match($get('type')) {
                                    'income' => 'income',
                                    default => 'expense',
                                })
                                ->whereDoesntHave('planItem', fn($q) => $q->where('is_reserve', true))
                                ->pluck('name', 'id')
                            )
                            ->live()
                            ->dehydrated(false)
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Názov hlavnej skupiny')
                                    ->required(),
                                Forms\Components\Select::make('financial_plan_item_id')
                                    ->label('Priradiť k pilieru')
                                    ->options(FinancialPlanItem::all()->pluck('name', 'id'))
                                    ->required(fn (\Filament\Forms\Get $get) => in_array($get('../../type'), ['expense', 'transfer']))
                                    ->visible(fn (\Filament\Forms\Get $get) => in_array($get('../../type'), ['expense', 'transfer'])),
                            ])
                            ->createOptionUsing(function (array $data, \Filament\Forms\Get $get) {
                                return Category::create([
                                    'user_id' => auth()->id(),
                                    'name' => $data['name'],
                                    'type' => $get('type') === 'income' ? 'income' : 'expense',
                                    'financial_plan_item_id' => $data['financial_plan_item_id'] ?? null,
                                ])->id;
                            }),

                        // 4. SELECT: PODKATEGÓRIA
                        Forms\Components\Select::make('category_id')
                            ->label('Podkategória / Detail')
                            ->placeholder(fn ($get) => $get('parent_category_id') ? 'Vyberte detail...' : 'Alebo vyberte zo zoznamu')
                            ->options(function ($get) {
                                $parentId = $get('parent_category_id');
                                $type = $get('type') === 'income' ? 'income' : 'expense';
                                
                                $query = Category::whereNotNull('parent_id')
                                    ->where('type', $type);
                                    
                                if ($parentId) {
                                    $query->where('parent_id', $parentId);
                                }
                                
                                return $query->with('parent')
                                    ->get()
                                    ->groupBy('parent.name')
                                    ->map(fn($categories) => $categories->pluck('name', 'id'))
                                    ->toArray();
                            })
                            ->required(fn ($get) => $get('type') !== 'transfer')
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Názov podkategórie')
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data, \Filament\Forms\Get $get) {
                                return Category::create([
                                    'user_id' => auth()->id(),
                                    'parent_id' => $get('parent_category_id'),
                                    'name' => $data['name'],
                                    'type' => $get('type') === 'income' ? 'income' : 'expense',
                                ])->id;
                            }),

                        // 5. SUMA
                        Forms\Components\TextInput::make('amount')
                            ->label('Suma')
                            ->numeric()
                            ->required()
                            ->prefixIcon(fn($get) => match ($get('type')) {
                                'expense' => 'heroicon-m-minus-circle',
                                'income' => 'heroicon-m-plus-circle',
                                default => 'heroicon-m-arrows-right-left',
                            })
                            ->prefixIconColor(fn($get) => match ($get('type')) {
                                'expense' => 'danger',
                                'income' => 'success',
                                'transfer' => 'gray',
                                default => 'gray',
                            }),

                        // 6. DÁTUM
                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Dátum')
                            ->default(now())
                            ->required(),

                        Forms\Components\TextInput::make('description')
                            ->label('Poznámka')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        FileUpload::make('attachment')
                            ->label('Bloček / Faktúra')
                            ->directory('transaction-attachments')
                            ->image()
                            ->imageEditor()
                            ->openable()
                            ->downloadable()
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Dátum')
                    ->date('d. m. Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Účet')
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategória')
                    ->formatStateUsing(function ($record) {
                        return $record->category?->parent 
                            ? $record->category->parent->name . ' > ' . $record->category->name 
                            : $record->category?->name;
                    })
                    ->placeholder('Bez kategórie')
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma')
                    ->sortable()
                    ->state(function (Transaction $record) {
                        $targetCurrencyCode = session('global_currency', 'EUR');
                        $targetCurrency = \App\Models\Currency::where('code', $targetCurrencyCode)->first();
                        
                        return \App\Services\CurrencyService::convert(
                            $record->amount,
                            $record->account->currency_id,
                            $targetCurrency?->id
                        );
                    })
                    ->formatStateUsing(function ($state) {
                        $currencyCode = session('global_currency', 'EUR');
                        $symbol = match($currencyCode) {
                            'USD' => '$',
                            'CZK' => 'Kč',
                            'GBP' => '£',
                            default => '€'
                        };
                        return number_format(abs((float)$state), 2, ',', ' ') . ' ' . $symbol;
                    })
                    ->description(function (Transaction $record) {
                        $globalCurrency = session('global_currency');
                        if ($globalCurrency && $globalCurrency !== $record->account->currency->code) {
                            return 'Pôvodne: ' . number_format(abs($record->amount), 2, ',', ' ') . ' ' . $record->account->currency->code;
                        }
                        return null;
                    })
                    ->color(fn($record) => match ($record->type?->value ?? $record->type) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'gray',
                        default => 'gray',
                    })
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge(),

                IconColumn::make('attachment')
                    ->label('Bloček')
                    ->icon('heroicon-o-paper-clip')
                    ->color('info')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('export_pdf')
                        ->label('Exportovať do PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (Collection $records) {
                            $pdf = Pdf::loadView('reports.transactions-pdf', [
                                'transactions' => $records,
                            ]);
                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'vypis-transakcii-' . now()->format('d-m-Y') . '.pdf');
                        }),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}