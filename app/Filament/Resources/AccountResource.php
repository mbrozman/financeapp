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

    protected static ?string $navigationGroup = '💸 OPERÁCIE';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('type', ['bank', 'cash']);
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
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('currency.code')
                    ->label('Mena'),

                Tables\Columns\TextColumn::make('balance')
                    ->label('Zostatok')
                    ->money(fn($record) => $record->currency->code) // Automaticky formátuje menu
                    ->sortable(),
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
