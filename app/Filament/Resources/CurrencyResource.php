<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CurrencyResource\Pages;
use App\Filament\Resources\CurrencyResource\RelationManagers;
use App\Models\Currency;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CurrencyResource extends Resource
{
    protected static ?string $model = Currency::class;

    public static function getNavigationLabel(): string
    {
        return 'Meny';
    }
    public static function getPluralLabel(): string
    {
        return 'Meny';
    }
    public static function getModelLabel(): string
    {
        return 'Mena';
    }
    protected static ?string $navigationGroup = '🔧 NASTAVENIA';
    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Kartička (Section) pre lepšie UX
                Forms\Components\Section::make('Detaily meny')
                    ->description('Zadajte základné informácie o mene.')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Kód meny (napr. EUR)')
                            ->required() // Toto pridá klientskú validáciu
                            ->unique(ignoreRecord: true) // Nedovolí duplicitné kódy
                            ->maxLength(3)
                            ->placeholder('EUR'),

                        Forms\Components\TextInput::make('name')
                            ->label('Názov meny')
                            ->required()
                            ->placeholder('Euro'),

                        Forms\Components\TextInput::make('symbol')
                            ->label('Symbol')
                            ->required()
                            ->placeholder('€'),

                        Forms\Components\TextInput::make('exchange_rate')
                            ->label('Výmenný kurz (voči hlavnej mene)')
                            ->numeric()
                            ->default(1.0)
                            ->required()
                            ->helperText('Ak je toto vaša hlavná mena, nechajte 1.0'),
                    ])->columns(2), // Rozdelí formulár na dva stĺpce
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('symbol'),
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->numeric(8) // Zobrazíme 8 desatinných miest
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Posledná aktualizácia')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencies::route('/'),
            'create' => Pages\CreateCurrency::route('/create'),
            'edit' => Pages\EditCurrency::route('/{record}/edit'),
        ];
    }
}
