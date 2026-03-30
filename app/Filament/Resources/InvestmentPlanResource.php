<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentPlanResource\Pages;
use App\Filament\Resources\InvestmentPlanResource\RelationManagers;
use App\Models\InvestmentPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvestmentPlanResource extends Resource
{
    protected static ?string $model = InvestmentPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = '📈 INVESTÍCIE';
    protected static ?string $navigationLabel = 'Investičné plány';
    protected static ?string $pluralLabel = 'Investičné plány';
    protected static ?string $modelLabel = 'Investičný plán';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Základné nastavenia')
                    ->description('Zadajte názov a odkiaľ sa majú peniaze na nákup čerpať.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Názov plánu')
                            ->placeholder('napr. Dôchodok, Deti, Nové auto')
                            ->required()
                            ->columnSpan(2),

                        Forms\Components\Select::make('account_id')
                            ->label('Zdrojový účet / Broker')
                            ->relationship('account', 'name', fn (Builder $query) => $query->where('user_id', auth()->id()))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('amount')
                            ->label('Suma nákupu')
                            ->numeric()
                            ->required()
                            ->suffix('EUR')
                            ->columnSpan(1),

                        Forms\Components\Select::make('frequency')
                            ->label('Frekvencia nákupu')
                            ->options([
                                'daily' => 'Denne',
                                'weekly' => 'Týždenne',
                                'monthly' => 'Mesačne',
                            ])
                            ->default('monthly')
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('next_run_date')
                            ->label('Dátum prvého nákupu')
                            ->default(now())
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Select::make('currency_id')
                            ->label('Mena nákupu')
                            ->relationship('currency', 'code')
                            ->default(fn() => \App\Models\Currency::where('code', 'EUR')->first()?->id)
                            ->required()
                            ->columnSpan(1),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Plán je aktívny')
                            ->default(true)
                            ->required()
                            ->columnSpan(1),
                    ])->columns(3),

                Forms\Components\Section::make('Zloženie portfólia')
                    ->description('Pridajte jedno alebo viac aktív a určite ich percentuálny podiel.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label(false)
                            ->afterStateHydrated(function ($component, $record) {
                                if ($record instanceof InvestmentPlan) {
                                    $items = $record->items->map(fn($item) => [
                                        'ticker' => $item->investment?->ticker,
                                        'weight' => (float)$item->weight
                                    ])->toArray();
                                    $component->state($items);
                                }
                            })
                            ->dehydrated(true)
                            ->schema([
                                Forms\Components\Select::make('ticker')
                                    ->label('Symbol (Ticker)')
                                    ->searchable()
                                    ->getSearchResultsUsing(fn(string $search) => app(\App\Services\StockApiService::class)->searchSymbols($search))
                                    ->getOptionLabelUsing(fn($value) => $value)
                                    ->required()
                                    ->live()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('weight')
                                    ->label('Váha (%)')
                                    ->numeric()
                                    ->default(100)
                                    ->minValue(1)
                                    ->maxValue(100)
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->addActionLabel('Pridať ďalšie aktívum')
                            ->reorderableWithButtons(),
                    ]),

                Forms\Components\Section::make('Počiatočný stav (nepovinné)')
                    ->description('Ak už plán nejaký čas beží, zadajte aktuálny stav pre korektné započítanie zisku.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Toggle::make('use_initial_state')
                            ->label('Zadať počiatočný stav')
                            ->live(),
                        Forms\Components\Grid::make(3)
                            ->visible(fn(Forms\Get $get) => $get('use_initial_state'))
                            ->schema([
                                Forms\Components\TextInput::make('initial_total_value')
                                    ->label('Aktuálna hodnota plánu')
                                    ->helperText('Napr. 10235 €')
                                    ->numeric()
                                    ->required(fn(Forms\Get $get) => $get('use_initial_state')),
                                Forms\Components\TextInput::make('initial_invested_amount')
                                    ->label('Celková investovaná suma')
                                    ->helperText('Napr. 10000 €')
                                    ->numeric()
                                    ->required(fn(Forms\Get $get) => $get('use_initial_state')),
                                Forms\Components\DatePicker::make('start_date')
                                    ->label('Dátum prvého nákupu')
                                    ->default(now()->subYear())
                                    ->required(fn(Forms\Get $get) => $get('use_initial_state')),
                            ]),
                    ]),

            ]);
    }

    public static function infolist(\Filament\Infolists\Infolist $infolist): \Filament\Infolists\Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Základné nastavenia')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(3)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('name')
                                    ->label('Pomenovanie plánu')
                                    ->weight('black')
                                    ->size('lg')
                                    ->color('primary')
                                    ->columnSpan(2),
                                \Filament\Infolists\Components\TextEntry::make('account.name')
                                    ->label('Účet / Broker')
                                    ->icon('heroicon-m-building-office-2')
                                    ->color('gray'),

                                \Filament\Infolists\Components\TextEntry::make('amount')
                                    ->label('Suma nákupu')
                                    ->money(fn($record) => $record->currency?->code ?? 'EUR')
                                    ->weight('bold'),
                                \Filament\Infolists\Components\TextEntry::make('frequency')
                                    ->label('Frekvencia')
                                    ->badge()
                                    ->color('primary')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'daily' => 'Denne',
                                        'weekly' => 'Týždenne',
                                        'monthly' => 'Mesačne',
                                        default => $state,
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('next_run_date')
                                    ->label('Nasledujúci nákup')
                                    ->date('d.m.Y'),
                            ]),
                    ]),

                \Filament\Infolists\Components\Section::make('Súhrnná výkonnosť plánu')
                    ->description('Tieto dáta sú agregované zo všetkých aktív patriacich do tohto plánu.')
                    ->schema([
                        \Filament\Infolists\Components\Grid::make(4)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('current_market_value_eur')
                                    ->label('Celková hodnota')
                                    ->state(fn($record) => $record->getCurrentMarketValueEur())
                                    ->money('EUR')
                                    ->weight('black')
                                    ->color('info')
                                    ->size('lg'),
                                \Filament\Infolists\Components\TextEntry::make('total_invested_eur')
                                    ->label('Celkovo vložené')
                                    ->state(fn($record) => $record->getTotalInvestedEur())
                                    ->money('EUR')
                                    ->color('gray'),
                                \Filament\Infolists\Components\TextEntry::make('total_gain_eur')
                                    ->label('Čistý zisk / strata')
                                    ->state(fn($record) => $record->getTotalGainEur())
                                    ->money('EUR')
                                    ->weight('bold')
                                    ->color(fn($record) => $record->getGainColor()),
                                \Filament\Infolists\Components\TextEntry::make('total_gain_percent')
                                    ->label('Výnos (%)')
                                    ->state(fn($record) => $record->getTotalGainPercent())
                                    ->formatStateUsing(fn($state) => number_format($state, 2, ',', ' ') . ' %')
                                    ->badge()
                                    ->color(fn($record) => $record->getGainColor()),
                            ]),
                    ]),

                \Filament\Infolists\Components\Section::make('Zloženie portfólia a detaily aktív')
                    ->schema([
                        \Filament\Infolists\Components\RepeatableEntry::make('items')
                            ->label(false)
                            ->schema([
                                \Filament\Infolists\Components\Grid::make(5)
                                    ->schema([
                                        \Filament\Infolists\Components\TextEntry::make('investment.ticker')
                                            ->label('Symbol')
                                            ->badge()
                                            ->color('warning'),
                                        \Filament\Infolists\Components\TextEntry::make('investment.name')
                                            ->label('Názov / Spoločnosť'),
                                        \Filament\Infolists\Components\TextEntry::make('weight')
                                            ->label('Váha v pláne')
                                            ->formatStateUsing(fn($state) => number_format($state, 0) . ' %'),
                                        \Filament\Infolists\Components\TextEntry::make('plan_market_value')
                                            ->label('Hodnota (EUR)')
                                            ->state(fn($record) => $record->investmentPlan?->getMarketValueForItem($record))
                                            ->money('EUR')
                                            ->weight('bold'),
                                        \Filament\Infolists\Components\TextEntry::make('plan_gain_percent')
                                            ->label('Výkonnosť')
                                            ->badge()
                                            ->state(fn($record) => $record->investmentPlan?->getGainPercentForItem($record))
                                            ->formatStateUsing(fn($state) => (($state > 0 ? '+' : '')) . number_format($state, 2, ',', ' ') . ' %')
                                            ->color(fn($record, $state) => $record->investmentPlan?->getGainColorForItem($record)),
                                    ]),
                            ])
                            ->grid(1)
                            ->contained(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Názov plánu')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('items')
                    ->label('Aktíva')
                    ->getStateUsing(fn ($record) => $record->items->map(fn($item) => $item->investment?->ticker . " (" . number_format($item->weight, 0) . "%)")->join(', '))
                    ->badge()
                    ->separator(', ')
                    ->searchable(),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Účet')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma')
                    ->money(fn ($record) => $record->currency?->code ?? 'EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Frekvencia')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'daily' => 'Denne',
                        'weekly' => 'Týždenne',
                        'monthly' => 'Mesačne',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('next_run_date')
                    ->label('Nasledujúci nákup')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktívny')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('frequency')
                    ->label('Frekvencia')
                    ->options([
                        'daily' => 'Denne',
                        'weekly' => 'Týždenne',
                        'monthly' => 'Mesačne',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Len aktívne'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvestmentPlans::route('/'),
            'create' => Pages\CreateInvestmentPlan::route('/create'),
            'view' => Pages\ViewInvestmentPlan::route('/{record}'),
            'edit' => Pages\EditInvestmentPlan::route('/{record}/edit'),
        ];
    }
}
