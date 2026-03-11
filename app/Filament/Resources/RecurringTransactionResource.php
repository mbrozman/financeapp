<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RecurringTransactionResource\Pages;
use App\Filament\Resources\RecurringTransactionResource\RelationManagers;
use App\Models\RecurringTransaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecurringTransactionResource extends Resource
{
    protected static ?string $model = RecurringTransaction::class;

    public static function getNavigationLabel(): string
    {
        return 'Pravidelné platby';
    }

    public static function getPluralLabel(): string
    {
        return 'Pravidelné platby';
    }

    public static function getModelLabel(): string
    {
        return 'Pravidelná platba';
    }

    protected static ?string $navigationGroup = 'Financie';

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    public static function canViewAny(): bool
    {
        return ! auth()->user()->is_superadmin;
    }
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Prvá sekcia: Základné nastavenie platby
                Forms\Components\Section::make('Nastavenie platby')
                    ->description('Definujte sumu a účet pre automatické generovanie.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Názov (napr. Netflix)')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('account_id')
                            ->label('Účet')
                            ->relationship('account', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategória')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Suma')
                            ->numeric()
                            ->required()
                            ->prefix('€'),

                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'income' => 'Príjem',
                                'expense' => 'Výdavok',
                            ])
                            ->required()
                            ->native(false),
                    ])->columns(2),

                // Druhá sekcia: Časovanie (Kedy sa to má stať?)
                Forms\Components\Section::make('Plánovanie')
                    ->description('Určite, ako často sa má platba opakovať.')
                    ->schema([
                        Forms\Components\Select::make('interval')
                            ->label('Interval opakovania')
                            ->options([
                                'daily' => 'Denne',
                                'weekly' => 'Týždenne',
                                'monthly' => 'Mesačne',
                                'yearly' => 'Ročne',
                            ])
                            ->required()
                            ->default('monthly')
                            ->native(false),

                        Forms\Components\DatePicker::make('next_date')
                            ->label('Dátum ďalšej platby')
                            ->required()
                            ->default(now())
                            ->helperText('V tento deň systém vytvorí prvú transakciu.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Automatika aktívna')
                            ->default(true)
                            ->onColor('success'),
                    ])->columns(3),
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

                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma')
                    ->money('EUR')
                    ->color(fn($record) => $record->type === 'expense' ? 'danger' : 'success'),

                Tables\Columns\TextColumn::make('interval')
                    ->label('Interval')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'daily' => 'Denne',
                        'weekly' => 'Týždenne',
                        'monthly' => 'Mesačne',
                        'yearly' => 'Ročne',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('next_date')
                    ->label('Najbližší termín')
                    ->date()
                    ->sortable(),

                // Rýchly prepínač aktivity priamo zo zoznamu
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktívne'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'income' => 'Príjmy',
                        'expense' => 'Výdavky',
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
            'index' => Pages\ListRecurringTransactions::route('/'),
            'create' => Pages\CreateRecurringTransaction::route('/create'),
            'edit' => Pages\EditRecurringTransaction::route('/{record}/edit'),
        ];
    }
}
