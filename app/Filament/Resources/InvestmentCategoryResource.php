<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentCategoryResource\Pages;
use App\Filament\Resources\InvestmentCategoryResource\RelationManagers;
use App\Models\InvestmentCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class InvestmentCategoryResource extends Resource
{
    protected static ?string $model = InvestmentCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Nastavenia';
    protected static ?string $label = 'Typ aktíva';
    protected static ?string $pluralLabel = 'Typy aktív';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail kategórie')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Názov (napr. Akcie, ETF, Krypto)')
                            ->required()
                            ->live(onBlur: true)
                            // UX: Automaticky vytvorí slug z názvu
                            ->afterStateUpdated(fn(Set $set, ?string $state) => $set('slug', Str::slug($state))),
                        
                        Forms\Components\Toggle::make('is_active')
                            ->label('Aktívny')
                            ->default(true),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('icon')
                            ->label('Ikona')
                            ->options([
                                'heroicon-o-chart-bar' => 'Graf / Akcie',
                                'heroicon-o-building-office' => 'Budova / Reality',
                                'heroicon-o-banknotes' => 'Peniaze / Hotovosť',
                                'heroicon-o-currency-bitcoin' => 'Krypto / Bitcoin',
                                'heroicon-o-globe-alt' => 'Svet / ETF',
                                'heroicon-o-academic-cap' => 'Vzdelanie / Iné',
                                'heroicon-o-bolt' => 'Energia / Tech',
                                'heroicon-o-shopping-cart' => 'Obchod / Spotreba',
                                'heroicon-o-heart' => 'Srdce / Zdravie',
                                'heroicon-o-briefcase' => 'Kufrík / Práca',
                                'heroicon-o-shield-check' => 'Štít / Dlhopisy',
                                'heroicon-o-sparkles' => 'Hviezdičky / Premium',
                            ])
                            ->default('heroicon-o-chart-bar')
                            ->required()
                            ->native(false)
                            ->prefixIcon(fn($get) => $get('icon') ?? 'heroicon-o-chart-bar')
                            ->live(),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Farba pre grafy')
                            ->default('#3b82f6'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('icon')
                    ->label('Ikona')
                    ->icon(fn(string $state): string => $state), // Toto vykreslí skutočnú ikonu

                Tables\Columns\TextColumn::make('name')
                    ->label('Názov')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Farba'),
                Tables\Columns\TextColumn::make('name')->label('Názov')->sortable()->searchable(),
                Tables\Columns\ColorColumn::make('color')->label('Farba'),
                Tables\Columns\TextColumn::make('investments_count')
                    ->label('Počet pozícií')
                    ->counts('investments'), // Ukáže koľko akcií máš v tejto kategórii
            ])
            ->filters([])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
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
            'index' => Pages\ListInvestmentCategories::route('/'),
            'create' => Pages\CreateInvestmentCategory::route('/create'),
            'edit' => Pages\EditInvestmentCategory::route('/{record}/edit'),
        ];
    }
}
