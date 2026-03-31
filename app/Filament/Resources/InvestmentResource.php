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
    protected static ?string $navigationGroup = '📈 INVESTÍCIE';
    protected static ?int $navigationSort = 30;
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
                                
                                // 1. Základná cena a názov
                                $data = app(StockApiService::class)->getLiveQuote($state);
                                if ($data) {
                                    $set('name', $data['name']);
                                    $set('current_price', (string)$data['price']);
                                    $set('initial_price', (string)$data['price']);

                                    // 1.5 Auto-nastavenie meny
                                    if (isset($data['currency'])) {
                                        $currency = \App\Models\Currency::where('code', $data['currency'])->first();
                                        if ($currency) {
                                            $set('currency_id', $currency->id);
                                        }
                                    }
                                }

                                // 2. Rozšírené info (Sektor, Krajina, Typ)
                                $profile = app(StockApiService::class)->getExtendedProfile($state);
                                if ($profile) {
                                    $set('asset_type', $profile['asset_type']);
                                }
                            }),

                        Forms\Components\TextInput::make('name')
                            ->label('Názov aktíva')
                            ->required(),

                        Forms\Components\Select::make('asset_type')
                            ->label('Trieda aktív')
                            ->options([
                                'Equity' => 'Akcie (Equity)',
                                'ETF' => 'Fond (ETF)',
                                'Crypto' => 'Kryptomena',
                                'Bond' => 'Dlhopis',
                                'Commodity' => 'Komodita',
                                'Other' => 'Iné',
                            ])
                            ->default('Equity'),



                        Forms\Components\Select::make('investment_category_id')
                            ->label('Typ aktíva')
                            ->relationship(
                                'category',
                                'name',
                                fn(Builder $query) =>
                                $query->where('user_id', auth()->id())
                                    ->where('is_active', true)
                            )->required()
                            ->preload(),

                        Forms\Components\Select::make('currency_id')
                            ->label('Domovská mena')
                            ->relationship('currency', 'code')
                            ->required(),

                        Forms\Components\Select::make('account_id')
                            ->label('Broker / Účet')
                            ->required()
                            ->live()
                            ->relationship(
                                'account',
                                'name',
                                fn(Builder $query) =>
                                $query->where('type', 'investment')
                                    ->where('is_active', true)
                            )
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
                        Forms\Components\Select::make('initial_currency_id')
                            ->label('Mena nákupu')
                            ->relationship('currency', 'code')
                            ->required()
                            ->live()
                            ->default(fn(Forms\Get $get) => $get('currency_id'))
                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                if ($state) {
                                    $rate = \App\Services\CurrencyService::getLiveRateById($state);
                                    $set('exchange_rate', (string)$rate);
                                }
                            }),
                        Forms\Components\TextInput::make('initial_quantity')
                            ->label('Počet kusov')
                            ->numeric()
                            ->required()
                            ->minValue(0.00000001)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calculateInitialCommission($set, $get)),
                        Forms\Components\TextInput::make('initial_price')
                            ->label(fn(Forms\Get $get) => "Cena za 1 ks (" . (\App\Models\Currency::find($get('initial_currency_id'))?->code ?? 'EUR') . ")")
                            ->numeric()
                            ->required()
                            ->minValue(0.00000001)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calculateInitialCommission($set, $get)),
                        Forms\Components\Grid::make(1)
                            ->schema([
                                Forms\Components\TextInput::make('initial_commission_percent')
                                    ->label('Poplatok v %')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn(Forms\Set $set, Forms\Get $get) => self::calculateInitialCommission($set, $get))
                                    ->suffix('%'),
                                Forms\Components\TextInput::make('initial_commission')
                                    ->label(fn(Forms\Get $get) => "Poplatok (" . (\App\Models\Currency::find($get('initial_currency_id'))?->code ?? 'EUR') . ")")
                                    ->numeric()
                                    ->default(0),
                            ])->columnSpan(1),
                        Forms\Components\TextInput::make('exchange_rate')
                            ->label(fn(Forms\Get $get) => "Kurz (1 " . (\App\Models\Currency::find($get('initial_currency_id'))?->code ?? 'EUR') . " = X EUR)")
                            ->numeric()
                            ->required()
                            ->default(fn(Forms\Get $get) => \App\Services\CurrencyService::getLiveRateById($get('initial_currency_id')))
                            ->helperText(fn(Forms\Get $get) => "Zadajte kurz pre menu " . (\App\Models\Currency::find($get('initial_currency_id'))?->code ?? 'EUR') . " voči EUR."),
                        Forms\Components\DatePicker::make('transaction_date')->label('Dátum nákupu')->default(now()),
                        Forms\Components\Toggle::make('subtract_from_broker')
                            ->label('Odpočítať z hotovosti u brokera')
                            ->default(true)
                            ->helperText('Ak je zapnuté, suma nákupu sa odpočíta z voľnej hotovosti na zvolenom účte (brokerovi). Vypnite pre historické dáta.'),
                    ])->columns(3),
            ]);
    }

    protected static function calculateInitialCommission(Forms\Set $set, Forms\Get $get): void
    {
        $percent = (float) ($get('initial_commission_percent') ?? 0);
        if ($percent <= 0) return;

        $qty = (float) ($get('initial_quantity') ?? 0);
        $price = (float) ($get('initial_price') ?? 0);

        if ($qty > 0 && $price > 0) {
            $absCommission = ($qty * $price) * ($percent / 100);
            $set('initial_commission', number_format($absCommission, 2, '.', ''));
        }
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

                Tables\Columns\TextColumn::make('asset_type')
                    ->label('Trieda')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'Equity' => 'info',
                        'ETF' => 'success',
                        'Crypto' => 'warning',
                        'Bond' => 'primary',
                        'Commodity' => 'warning',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Broker')
                    ->icon('heroicon-m-building-office-2')
                    ->color('gray')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Typ')
                    ->weight('bold')
                    ->extraAttributes(fn($record) => ['style' => 'color: ' . ($record->category?->color ?? '#808080')])
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Ks')
                    ->numeric(4)
                    ->alignEnd(),

                // PRIEMERNÁ NÁKUPNÁ CENA
                Tables\Columns\TextColumn::make('average_buy_price')
                    ->label('Nákupná cena')
                    ->alignEnd()
                    ->state(fn($record) => $record->getAveragePriceForCurrency(session('global_currency')))
                    ->formatStateUsing(function ($state, $record) {
                        $currencyCode = session('global_currency');
                        $symbol = $currencyCode 
                            ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                            : ($record->currency?->symbol ?? '');
                        return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                    }),

                // % Z PORTFÓLIA
                Tables\Columns\TextColumn::make('portfolio_weight')
                    ->label('% portfólia')
                    ->alignEnd()
                    ->state(function (Investment $record) {
                        static $totalValue = null;
                        if ($totalValue === null) {
                            $totalValue = Investment::where('user_id', auth()->id())
                                ->where('is_archived', false)
                                ->get()
                                ->sum(fn($i) => (float) $i->current_market_value_eur);
                        }
                        if ($totalValue <= 0 || $record->is_archived) return '0.00 %';
                        $weight = ((float) $record->current_market_value_eur / $totalValue) * 100;
                        return number_format($weight, 2, ',', ' ') . ' %';
                    })
                    ->badge()
                    ->color('gray')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('current_market_value_eur', $direction)),

                Tables\Columns\TextColumn::make('total_invested_base')
                    ->label('Investované')
                    ->alignEnd()
                    ->state(fn($record) => $record->getInvestedForCurrency(session('global_currency')))
                    ->formatStateUsing(function ($state, $record) {
                        $currencyCode = session('global_currency');
                        $symbol = $currencyCode 
                            ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                            : ($record->currency?->symbol ?? '');
                        return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                    }),

                // VÝNOS %
                Tables\Columns\TextColumn::make('yield_percent')
                    ->label('Výnos (%)')
                    ->alignEnd()
                    ->state(function (Investment $record) {
                        $invested = (float) $record->total_invested_eur;
                        if ($invested <= 0) return '0.00 %';
                        $gain = (float) $record->getGainForCurrency('EUR');
                        $yield = ($gain / $invested) * 100;
                        return number_format($yield, 2, ',', ' ') . ' %';
                    })
                    ->badge()
                    ->color(fn($state) => (float)str_replace(',', '.', $state) < 0 ? 'danger' : ((float)str_replace(',', '.', $state) > 0 ? 'success' : 'gray')),

                // ZISK V MENE
                Tables\Columns\TextColumn::make('total_gain_base')
                    ->label('Zisk/Strata')
                    ->alignEnd()
                    ->weight('bold')
                    ->state(fn($record) => $record->getGainForCurrency(session('global_currency')))
                    ->color(fn($state) => (float)$state < 0 ? 'danger' : ((float)$state > 0 ? 'success' : 'gray'))
                    ->formatStateUsing(function ($state, $record) {
                        $currencyCode = session('global_currency');
                        $symbol = $currencyCode 
                            ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                            : ($record->currency?->symbol ?? '');
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
                    ->state(fn($record) => $record->getCurrentValueForCurrency(session('global_currency')))
                    ->formatStateUsing(function ($state, $record) {
                        $currencyCode = session('global_currency');
                        $symbol = $currencyCode 
                            ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                            : ($record->currency?->symbol ?? '');
                        return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                    }),

                Tables\Columns\TextColumn::make('tax_status')
                    ->label('Daňový test')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'Oslobodené' => 'success',
                        'Zdaniteľné' => 'danger',
                        default => 'warning'
                    }),
            ])
            ->recordUrl(fn($record) => static::getUrl('view', ['record' => $record]))
            ->defaultSort('ticker', 'asc')
            ->groups([
                Tables\Grouping\Group::make('account.name')
                    ->label('Broker')
                    ->collapsible(),
            ])
            ->filters([
                // Filtre odstránené na žiadosť používateľa, nahrádza ich vyhľadávanie
            ]);
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
                        Infolists\Components\TextEntry::make('account.name')->label('Broker'),
                        Infolists\Components\TextEntry::make('asset_type')
                            ->label('Trieda')
                            ->badge()
                            ->color(fn($state) => match($state) {
                                'Equity' => 'info',
                                'ETF' => 'success',
                                'Crypto' => 'warning',
                                'Bond' => 'primary',
                                'Commodity' => 'warning',
                                default => 'gray',
                            }),


                        Infolists\Components\TextEntry::make('market_value')
                            ->label(function ($record) {
                                $currencyCode = session('global_currency');
                                $suffix = $currencyCode ? " ({$currencyCode})" : '';
                                return ($record->is_archived ? 'Realizované tržby' : 'Trhová hodnota') . $suffix;
                            })
                            ->state(fn($record) => $record->getCurrentValueForCurrency(session('global_currency')))
                            ->formatStateUsing(function ($state, $record) {
                                $currencyCode = session('global_currency');
                                $symbol = $currencyCode 
                                    ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                                    : ($record->currency?->symbol ?? '');
                                return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                            })
                            ->weight('black')->color('info'),

                        Infolists\Components\TextEntry::make('total_invested_base')
                            ->label(function() {
                                $currencyCode = session('global_currency');
                                return $currencyCode ? "Celková investícia ({$currencyCode})" : 'Celková investícia';
                            })
                            ->state(fn($record) => $record->getInvestedForCurrency(session('global_currency')))
                            ->formatStateUsing(function ($state, $record) {
                                $currencyCode = session('global_currency');
                                $symbol = $currencyCode 
                                    ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                                    : ($record->currency?->symbol ?? '');
                                return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                            }),

                        Infolists\Components\TextEntry::make('total_quantity')
                            ->label('Vlastnené kusy')
                            ->numeric(4)
                            ->visible(fn($record) => !$record->is_archived),

                        Infolists\Components\TextEntry::make('current_price')
                            ->label(function() {
                                $currencyCode = session('global_currency');
                                return $currencyCode ? "Aktuálna cena ({$currencyCode} / ks)" : 'Aktuálna cena (ks)';
                            })
                            ->state(function ($record) {
                                $currencyCode = session('global_currency');
                                if ($currencyCode === 'EUR') {
                                    return \App\Services\CurrencyService::convertToEur($record->current_price, $record->currency_id);
                                }
                                if ($currencyCode && $currencyCode !== $record->currency?->code) {
                                    $targetCurrency = \App\Models\Currency::where('code', $currencyCode)->first();
                                    return \App\Services\CurrencyService::convert($record->current_price, $record->currency_id, $targetCurrency?->id);
                                }
                                return $record->current_price;
                            })
                            ->formatStateUsing(function ($state, $record) {
                                $currencyCode = session('global_currency');
                                $symbol = $currencyCode 
                                    ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                                    : ($record->currency?->symbol ?? '');
                                return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                            })
                            ->weight('bold')
                            ->visible(fn($record) => !$record->is_archived),

                        Infolists\Components\TextEntry::make('average_buy_price_base')
                            ->label(function() {
                                $currencyCode = session('global_currency');
                                return $currencyCode ? "Priemerná nákupka ({$currencyCode})" : 'Priemerná nákupka';
                            })
                            ->state(function($record) {
                                $currencyCode = session('global_currency') ?? $record->currency?->code;
                                if ($currencyCode === 'EUR') return $record->average_buy_price_eur;
                                
                                // Pre natívnu menu (USD) vrátime priamo nákupku
                                if ($currencyCode === $record->currency?->code) {
                                    return $record->average_buy_price_base;
                                }

                                // Pre ostatné (CZK) prepočítame EUR nákupku na cieľovú menu (najlepšia aproximácia)
                                $targetCurrency = \App\Models\Currency::where('code', $currencyCode)->first();
                                return \App\Services\CurrencyService::convert($record->average_buy_price_eur, null, $targetCurrency?->id);
                            })
                            ->formatStateUsing(function ($state, $record) {
                                $currencyCode = session('global_currency');
                                $symbol = $currencyCode 
                                    ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                                    : ($record->currency?->symbol ?? '');
                                return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                            }),

                        Infolists\Components\TextEntry::make('gain_base')
                            ->label(function() {
                                $currencyCode = session('global_currency');
                                return $currencyCode ? "Výsledok P/L ({$currencyCode})" : 'Výsledok P/L';
                            })
                            ->state(fn($record) => $record->getGainForCurrency(session('global_currency')))
                            ->formatStateUsing(function ($state, $record) {
                                $currencyCode = session('global_currency');
                                $symbol = $currencyCode 
                                    ? (\App\Models\Currency::where('code', $currencyCode)->first()?->symbol ?? $currencyCode)
                                    : ($record->currency?->symbol ?? '');
                                return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                            })
                            ->weight('black')
                            ->color(fn($state) => (float)$state >= 0 ? 'success' : 'danger'),

                        Infolists\Components\TextEntry::make('last_price_update')
                            ->label('Posledná aktualizácia')
                            ->formatStateUsing(fn($state) => $state ? $state->diffForHumans() : 'Nikdy')
                            ->dateTime('d. m. Y H:i:s')
                            ->badge()
                            ->color(fn($state) => ($state && $state->gt(now()->subHours(2))) ? 'success' : 'warning'),

                    ])->columns(5),

                Infolists\Components\Section::make('Daňový asistent (Time-test 1 rok)')
                    ->schema([
                        Infolists\Components\TextEntry::make('tax_status')
                            ->label('Aktuálny status')
                            ->badge()
                            ->color(fn($state) => match($state) {
                                'Oslobodené' => 'success',
                                'Zdaniteľné' => 'danger',
                                default => 'warning'
                            }),
                        Infolists\Components\TextEntry::make('tax_free_quantity')
                            ->label('Množstvo oslobodené od dane')
                            ->formatStateUsing(fn($state) => number_format((float)$state, 4, ',', ' ') . ' ks')
                            ->color('success'),
                        Infolists\Components\TextEntry::make('tax_free_percent')
                            ->label('Pomer portfólia v bezpečí')
                            ->formatStateUsing(fn($state) => number_format((float)$state, 2, ',', ' ') . ' %')
                            ->badge()
                            ->color(fn($state) => (float)$state >= 100 ? 'success' : 'warning'),
                        Infolists\Components\TextEntry::make('next_tax_free_date')
                            ->label('Najbližšie uvoľnenie (ďalšia várka)')
                            ->dateTime('d. m. Y')
                            ->placeholder('Žiadne ďalšie nákupy nečakajú')
                            ->icon('heroicon-m-calendar-days')
                            ->color('info'),
                    ])->columns(4)
                    ->visible(fn($record) => !$record->is_archived),
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
        return $record->user_id === auth()->id();
    }
}
