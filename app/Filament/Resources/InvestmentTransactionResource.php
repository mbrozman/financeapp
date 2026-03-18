<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentTransactionResource\Pages;
use App\Models\InvestmentTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\TransactionType;
use App\Services\CurrencyService;

class InvestmentTransactionResource extends Resource
{
    protected static ?string $model = InvestmentTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationGroup = 'Investície';
    
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    protected static ?string $label = 'Pohyb na majetku';
    protected static ?string $pluralLabel = 'História pohybov';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Základné info')
                    ->schema([
                        Forms\Components\Select::make('investment_id')
                            ->label('Investícia (Ticker)')
                            ->relationship('investment', 'ticker')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('type')
                            ->label('Typ transakcie')
                            ->options([
                                'buy' => 'Nákup',
                                'sell' => 'Predaj',
                                'dividend' => 'Dividenda',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Dátum transakcie')
                            ->default(now())
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Finančné údaje')
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Počet kusov')
                            ->numeric()
                            ->required()
                            ->step(0.00000001), // Pre krypto/zlomkové akcie

                        Forms\Components\TextInput::make('price_per_unit')
                            ->label('Cena za 1 ks')
                            ->numeric()
                            ->required()
                            ->prefixIcon('heroicon-o-currency-dollar'),

                        Forms\Components\TextInput::make('commission')
                            ->label('Poplatok brokerovi')
                            ->numeric()
                            ->default(0)
                            ->prefixIcon('heroicon-o-receipt-percent'),

                        Forms\Components\Select::make('currency_id')
                            ->label('Mena transakcie')
                            ->relationship('currency', 'code')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('exchange_rate', CurrencyService::getLiveRateById($state)))
                            ->default(fn() => \App\Models\Currency::where('code', 'USD')->value('id') ?? 1)
                            ->required(),

                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('Kurz (1 mena = X EUR)')
                            ->numeric()
                            ->default(fn (Forms\Get $get) => CurrencyService::getLiveRateById($get('currency_id')))
                            ->required()
                            ->helperText('Príklad pre USD: 0.92 znamená, že 1 USD = 0.92 EUR'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')->label('Dátum')->date()->sortable(),
                Tables\Columns\TextColumn::make('investment.ticker')->label('Ticker')->badge(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge(),

                Tables\Columns\TextColumn::make('quantity')->label('Ks')->numeric(4),
                Tables\Columns\TextColumn::make('price_per_unit')->label('Cena/ks')->sortable(),
                Tables\Columns\TextColumn::make('currency.code')
                    ->label('Mena')
                    ->badge()
                    ->color('gray')
            ])
            ->defaultSort('transaction_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvestmentTransactions::route('/'),
            'create' => Pages\CreateInvestmentTransaction::route('/create'),
            'edit' => Pages\EditInvestmentTransaction::route('/{record}/edit'),
        ];
    }
    public static function canCreate(): bool
    {
        return false;
    }

    // Ak chceš, aby sa história nedala ani mazať/upravovať (kvôli integrite dát):
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
