<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers;
use App\Models\Account;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    public static function getNavigationLabel(): string
    {
        return 'Banky a Hotovosť';
    }
    public static function getPluralLabel(): string
    {
        return 'Banky a Hotovosť';
    }
    public static function getModelLabel(): string
    {
        return 'Účet';
    }

    protected static ?string $navigationGroup = '🔧 NASTAVENIA';
    protected static ?int $navigationSort = 100;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', ['bank', 'cash', 'reserve']);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Názov účtu')->required(),
            Forms\Components\Select::make('type')
                ->label('Typ')
                ->options([
                    'bank' => 'Bankový účet',
                    'cash' => 'Hotovosť',
                    'reserve' => 'Rezerva (fond)',
                ])->required(),
            Forms\Components\Select::make('currency_id')
                ->label('Mena')->relationship('currency', 'code')->required(),
            Forms\Components\TextInput::make('balance')->label('Zostatok')->numeric()->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Názov')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge() // Zobrazí typ ako farebný štítok
                    ->color(fn(string $state): string => match ($state) {
                        'bank' => 'info',
                        'crypto' => 'warning',
                        'investment' => 'success',
                        'cash' => 'gray',
                        'reserve' => 'primary',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('currency.code')
                    ->label('Mena'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Zostatok')
                    ->sortable()
                    ->state(function (Account $record) {
                        $targetCurrencyCode = session('global_currency', 'EUR');
                        $targetCurrency = \App\Models\Currency::where('code', $targetCurrencyCode)->first();
                        
                        return \App\Services\CurrencyService::convert(
                            $record->balance,
                            $record->currency_id,
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
                        return number_format((float)$state, 2, ',', ' ') . ' ' . $symbol;
                    })
                    ->description(function (Account $record) {
                        $globalCurrency = session('global_currency');
                        if ($globalCurrency && $globalCurrency !== $record->currency->code) {
                            return 'Pôvodne: ' . number_format($record->balance, 2, ',', ' ') . ' ' . $record->currency->code;
                        }
                        return null;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'bank' => 'Banka',
                        'crypto' => 'Krypto',
                        'investment' => 'Investície',
                    ]),
            ]);
    }



    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
