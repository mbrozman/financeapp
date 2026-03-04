<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentResource\Pages;
use App\Filament\Resources\InvestmentResource\RelationManagers\TransactionsRelationManager;
use App\Models\Investment;
use App\Services\StockApiService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Brick\Math\BigDecimal;

class InvestmentResource extends Resource
{
    protected static ?string $model = Investment::class;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string { return 'Investície'; }
    public static function getPluralLabel(): string { return 'Investície'; }
    public static function getModelLabel(): string { return 'Investícia'; }
    protected static ?string $navigationGroup = 'Investície';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Založenie pozície')
                    ->schema([
                        Forms\Components\Select::make('ticker')
                            ->label('Ticker (Symbol)')
                            ->searchable()
                            ->getSearchResultsUsing(fn(string $search) => app(StockApiService::class)->searchSymbols($search))
                            ->getOptionLabelUsing(fn($value) => $value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (!$state) return;
                                $data = app(StockApiService::class)->getLiveQuote($state);
                                if ($data) {
                                    $set('name', $data['name']);
                                    $set('current_price', (string)$data['price']);
                                    $set('initial_price', (string)$data['price']);
                                }
                            }),

                        Forms\Components\TextInput::make('name')
                            ->label('Názov aktíva')
                            ->required(),

                        Forms\Components\Select::make('investment_category_id')
                            ->label('Typ aktíva')
                            ->relationship('category', 'name')
                            ->required()
                            ->preload(),

                        Forms\Components\Select::make('currency_id')
                            ->label('Domovská mena')
                            ->relationship('currency', 'code')
                            ->required(),

                        Forms\Components\Select::make('account_id')
                            ->label('Broker / Účet')
                            ->relationship('account', 'name', fn($query) => $query->where('type', 'investment'))
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                $account = \App\Models\Account::find($state);
                                if ($account) {
                                    $set('broker', $account->name);
                                }
                            }),

                        Forms\Components\Hidden::make('broker')->required(),
                        Forms\Components\Hidden::make('current_price')->default('0'),
                    ])->columns(2),

                Forms\Components\Section::make('Prvý nákup')
                    ->visible(fn($livewire) => $livewire instanceof Pages\CreateInvestment)
                    ->schema([
                        Forms\Components\TextInput::make('initial_quantity')->label('Počet kusov')->numeric()->default(0),
                        Forms\Components\TextInput::make('initial_price')->label('Cena za 1 ks')->numeric()->default(0),
                        Forms\Components\TextInput::make('initial_commission')->label('Poplatok')->numeric()->default(0),
                        Forms\Components\DatePicker::make('transaction_date')->label('Dátum nákupu')->default(now()),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticker')
                    ->label('Symbol')
                    ->badge()
                    ->color('warning')
                    ->searchable(),

                Tables\Columns\TextColumn::make('broker')
                    ->label('Broker')
                    ->icon('heroicon-m-building-office-2')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Typ')
                    ->weight('bold')
                    ->extraAttributes(fn($record) => ['style' => 'color: ' . ($record->category?->color ?? '#808080')]),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Ks')
                    ->numeric(4)
                    ->hidden(fn($livewire) => $livewire->activeTab === 'archived'),

                Tables\Columns\TextColumn::make('total_invested_base')
                    ->label('Investované')
                    ->alignEnd()
                    ->formatStateUsing(fn($state, $record) => 
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '')
                    ),

                // ZISK V MENE POZÍCIE
                Tables\Columns\TextColumn::make('total_gain_base')
                    ->label('Zisk/Strata')
                    ->alignEnd()
                    ->weight('bold')
                    // Matematicky bezpečné určenie farby: ak reťazec nezačína mínusom a nie je nula
                    ->color(fn($state) => str_contains((string)$state, '-') ? 'danger' : ((float)$state > 0 ? 'success' : 'gray'))
                    ->formatStateUsing(function ($state, $record) {
                        $symbol = $record->currency?->symbol ?? '$';
                        $val = (float)$state;
                        $prefix = $val > 0 ? '+' : '';
                        return $prefix . number_format($val, 2, ',', ' ') . ' ' . $symbol;
                    }),

                // HODNOTA
                Tables\Columns\TextColumn::make('current_market_value_base')
                    ->label(fn($livewire) => $livewire->activeTab === 'archived' ? 'Predané za' : 'Hodnota')
                    ->alignEnd()
                    ->weight('black')
                    ->color(fn($record) => $record->is_archived ? 'gray' : 'info')
                    ->state(fn($record) => $record->is_archived ? $record->total_sales_base : $record->current_market_value_base)
                    ->formatStateUsing(fn($state, $record) => 
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '')
                    ),

                // VÝNOS %
                Tables\Columns\TextColumn::make('total_gain_percent')
                    ->label('Výnos (%)')
                    ->alignEnd()
                    ->badge()
                    ->color(fn($state) => (float)$state > 0 ? 'success' : ((float)$state < 0 ? 'danger' : 'gray'))
                    ->formatStateUsing(fn($state) => ((float)$state > 0 ? '+' : '') . number_format((float)$state, 2, ',', ' ') . ' %'),
            ])
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('ticker', 'asc')
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Prehľad pozície')
                    ->schema([
                        Infolists\Components\TextEntry::make('ticker')->label('Ticker')->badge(),
                        Infolists\Components\TextEntry::make('name')->label('Názov spoločnosti'),
                        Infolists\Components\TextEntry::make('is_archived')
                            ->label('Stav')->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Ukončené' : 'Aktívne')
                            ->color(fn($state) => $state ? 'gray' : 'success'),
                        Infolists\Components\TextEntry::make('category.name')->label('Typ aktíva'),
                        Infolists\Components\TextEntry::make('broker')->label('Broker'),

                        Infolists\Components\TextEntry::make('market_value')
                            ->label(fn($record) => $record->is_archived ? 'Realizované tržby' : 'Trhová hodnota')
                            ->state(fn($record) => $record->is_archived ? $record->total_sales_base : $record->current_market_value_base)
                            ->formatStateUsing(fn($state, $record) => number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? ''))
                            ->weight('black')->color('info'),

                        Infolists\Components\TextEntry::make('total_invested_base')
                            ->label('Celková investícia')
                            ->formatStateUsing(fn($state, $record) => number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '')),

                        Infolists\Components\TextEntry::make('total_quantity')
                            ->label('Vlastnené kusy')
                            ->numeric(4)
                            ->visible(fn($record) => !$record->is_archived),

                        Infolists\Components\TextEntry::make('current_price')
                            ->label('Aktuálna cena (ks)')
                            ->formatStateUsing(fn($state, $record) => number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? ''))
                            ->weight('bold')
                            ->visible(fn($record) => !$record->is_archived),

                        Infolists\Components\TextEntry::make('average_buy_price_base')
                            ->label('Priemerná nákupka')
                            ->formatStateUsing(fn($state, $record) => number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '')),

                        Infolists\Components\TextEntry::make('tax_free_quantity')
                            ->label('Oslobodené od dane (1r)')
                            ->formatStateUsing(fn($state) => number_format((float)$state, 2, ',', ' ') . ' ks')
                            ->color('success')
                            ->visible(fn($record) => !$record->is_archived),

                        Infolists\Components\TextEntry::make('last_price_update')
                            ->label('Čerstvosť dát')
                            ->formatStateUsing(fn($state) => $state ? $state->diffForHumans() : 'Nikdy')
                            ->badge()
                            ->color(fn($state) => $state && $state->gt(now()->subHour()) ? 'success' : 'warning'),

                    ])->columns(5),
            ]);
    }

    public static function getRelations(): array { return [TransactionsRelationManager::class]; }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvestments::route('/'),
            'create' => Pages\CreateInvestment::route('/create'),
            'view' => Pages\ViewInvestment::route('/{record}'),
            'edit' => Pages\EditInvestment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['transactions', 'category', 'account', 'currency'])
            ->where('broker', '!=', 'System');
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return $record->user_id === auth()->id();
    }
}