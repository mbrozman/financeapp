<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentResource\Pages;
use App\Filament\Resources\InvestmentResource\RelationManagers\TransactionsRelationManager;
use App\Models\Investment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class InvestmentResource extends Resource
{
    protected static ?string $model = Investment::class;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return 'Investície';
    }
    public static function getPluralLabel(): string
    {
        return 'Investície';
    }
    public static function getModelLabel(): string
    {
        return 'Investícia';
    }
    protected static ?string $navigationGroup = 'Investície';
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // SEKCIA 1: ZÁKLADNÉ ÚDAJE (Ukladajú sa do tabuľky investments)
                Forms\Components\Section::make('Založenie pozície')
                    ->schema([
                        Forms\Components\Select::make('ticker')
                            ->label('Ticker (Symbol)')
                            ->searchable()
                            ->getSearchResultsUsing(fn(string $search) => (new \App\Services\StockApiService())->searchSymbols($search))
                            ->getOptionLabelUsing(fn($value) => $value)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if (!$state) return;

                                $data = (new \App\Services\StockApiService())->getLiveQuote($state);

                                if ($data) {
                                    // 1. AUTOMATICKY VYPLNÍME NÁZOV Z API
                                    $set('name', $data['name']);

                                    // 2. VYPLNÍME CENY
                                    $set('current_price', $data['price']);
                                    $set('initial_price', $data['price']);
                                }
                            }),

                        Forms\Components\TextInput::make('name')
                            ->label('Názov aktíva') // NOVÉ COPY
                            ->placeholder('napr. Apple Inc.')
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
                            ->label('Broker (Hotovosť)')
                            ->relationship('account', 'name', fn($query) => $query->where('type', 'investment'))
                            ->required(),

                        Forms\Components\TextInput::make('broker')
                            ->label('Identifikátor (napr. XTB #1)')
                            ->required(),

                        Forms\Components\Hidden::make('current_price')->default(0),
                    ])->columns(2),

                // SEKCIA 2: PRVÝ NÁKUP (Pomocné polia - NIE SÚ V DB INVESTMENTS)
                Forms\Components\Section::make('Prvý nákup')
                    ->description('Tieto údaje vytvoria prvý záznam v histórii nákupov.')
                    // Zobrazí sa len pri vytváraní novej akcie
                    ->visible(fn($livewire) => $livewire instanceof Pages\CreateInvestment)
                    ->schema([
                        Forms\Components\TextInput::make('initial_quantity')
                            ->label('Počet kusov')
                            ->numeric()
                            ->required()
                            // TENTO RIADOK JE KĽÚČOVÝ:
                            // Hovorí Filamentu: "Zober túto hodnotu, ale NEHĽADAJ pre ňu stĺpec v DB tabuľke investments"
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('initial_price')
                            ->label('Cena za 1 ks')
                            ->numeric()
                            ->required()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('initial_commission')
                            ->label('Poplatok')
                            ->numeric()
                            ->default(0)
                            ->dehydrated(false),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Dátum nákupu')
                            ->default(now())
                            ->dehydrated(false),
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
                    ->color('gray')
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Typ')
                    ->weight('bold')
                    ->extraAttributes(fn($record) => ['style' => 'color: ' . ($record->category?->color ?? '#808080')]),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Ks')
                    ->numeric(2)
                    ->hidden(fn($livewire) => $livewire->activeTab === 'archived'),

                // INVESTOVANÉ (V DOMOVSKEJ MENE)
                Tables\Columns\TextColumn::make('total_invested_base')
                    ->label('Investované')
                    ->alignEnd()
                    ->formatStateUsing(
                        fn($state, $record) =>
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '')
                    ),

                // HODNOTA (DYNAMICKÁ PODĽA STAVU)
                Tables\Columns\TextColumn::make('current_market_value_base')
                    ->label(fn($livewire) => $livewire->activeTab === 'archived' ? 'Predané za' : 'Hodnota')
                    ->alignEnd()
                    ->weight('black')
                    ->color(fn($record) => $record->is_archived ? 'gray' : 'info')
                    // Trik: Ak je archivovaná, prepneme hodnotu na SalesBase
                    ->state(fn($record) => $record->is_archived ? $record->total_sales_base : $record->current_market_value_base)
                    ->formatStateUsing(
                        fn($state, $record) =>
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '')
                    ),

                // VÝNOS V % (Počítaný v domovskej mene)
                Tables\Columns\TextColumn::make('gain_pct')
                    ->label('Výnos (%)')
                    ->alignEnd()
                    ->state(function ($record) {
                        $invested = (float)$record->total_invested_base;
                        if ($invested <= 0) return 0;
                        $current = $record->is_archived ? (float)$record->total_sales_base : (float)$record->current_market_value_base;
                        return (($current - $invested) / $invested) * 100;
                    })
                    ->formatStateUsing(fn($state) => ($state >= 0 ? '+' : '') . number_format($state, 2, ',', ' ') . ' %')
                    ->weight('black')
                    ->color(fn($state) => (float)$state >= 0 ? 'success' : 'danger'),
            ])
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort(column: 'ticker')
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Prehľad pozície')
                    ->schema([
                        // 1. IDENTIFIKÁCIA
                        Infolists\Components\TextEntry::make('ticker')
                            ->label('Ticker')
                            ->badge(),

                        Infolists\Components\TextEntry::make('name')
                            ->label('Názov spoločnosti'),

                        Infolists\Components\TextEntry::make('is_archived')
                            ->label('Stav')
                            ->badge()
                            ->formatStateUsing(fn($state) => $state ? 'Ukončené' : 'Aktívne')
                            ->color(fn($state) => $state ? 'gray' : 'success'),

                        Infolists\Components\TextEntry::make('category.name')
                            ->label('Typ aktíva'),

                        Infolists\Components\TextEntry::make('broker')
                            ->label('Broker'),

                        // 2. FINANCIE (Všetky premenné premenované z $r na $record)
                        Infolists\Components\TextEntry::make('market_value')
                            ->label(fn($record) => $record->is_archived ? 'Predajná cena (Tržby)' : 'Trhová hodnota')
                            ->state(fn($record) => $record->is_archived ? $record->total_sales_base : $record->current_market_value_base)
                            ->formatStateUsing(fn($state, $record) => number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? ''))
                            ->weight('black')
                            ->color('info'),

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
                            ->formatStateUsing(fn($state) => number_format((float)$state, 2) . ' ks')
                            ->color('success')
                            ->visible(fn($record) => !$record->is_archived),

                        Infolists\Components\TextEntry::make('last_price_update')
                            ->label('Čerstvosť dát')
                            ->since() // Automaticky zobrazí "pred X minútami"
                            ->badge()
                            ->color(fn($state) => $state && $state->gt(now()->subHour()) ? 'success' : 'warning')
                            ->icon(fn($state) => $state && $state->gt(now()->subHour()) ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle'),

                    ])->columns(5),
            ]);
    }

    public static function getRelations(): array
    {
        return [TransactionsRelationManager::class];
    }

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
        return true;
    }
}
