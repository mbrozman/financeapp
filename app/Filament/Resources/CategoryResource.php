<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\CategoryResource\RelationManagers;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Nastavenia';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Nastavenie hlavnej kategórie')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Názov')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->label('Typ kategórie')
                    ->options([
                        'expense' => 'Výdavok',
                        'income' => 'Príjem',
                    ])
                    ->default('expense')
                    ->required()
                    ->native(false)
                    ->live(),
                
                Forms\Components\Select::make('financial_plan_item_id')
                    ->label('Finančný pilier')
                    ->relationship('planItem', 'name')
                    ->required(fn (\Filament\Forms\Get $get) => $get('type') === 'expense')
                    ->visible(fn (\Filament\Forms\Get $get) => $get('type') === 'expense'),

                Forms\Components\ColorPicker::make('color')
                    ->label('Farba')
                    ->required()
                    ->default('#34d399'),
                
                Forms\Components\Select::make('icon')
                    ->label('Ikona')
                    ->options([
                        'heroicon-o-home' => 'Domov / Bývanie',
                        'heroicon-o-shopping-cart' => 'Nákupy / Strava',
                        'heroicon-o-truck' => 'Auto / Doprava',
                        'heroicon-o-heart' => 'Zdravie / Krása',
                        'heroicon-o-academic-cap' => 'Vzdelanie',
                        'heroicon-o-briefcase' => 'Práca / Podnikanie',
                        'heroicon-o-banknotes' => 'Peniaze / Hotovosť',
                        'heroicon-o-building-office' => 'Budova / Reality',
                        'heroicon-o-chart-bar' => 'Graf / Investície',
                        'heroicon-o-shield-check' => 'Štít / Poistenie',
                        'heroicon-o-bolt' => 'Energia / Tech',
                        'heroicon-o-globe-alt' => 'Svet / Cestovanie',
                        'heroicon-o-gift' => 'Darčeky / Zábava',
                        'heroicon-o-sparkles' => 'Hviezdičky / Premium',
                        'heroicon-o-tag' => 'Štítok / Všeobecné',
                        'heroicon-o-currency-dollar' => 'Mena / Financie',
                        'heroicon-o-puzzle-piece' => 'Skladačka / Ostatné',
                    ])
                    ->default('heroicon-o-tag')
                    ->native(false)
                    ->prefixIcon(fn($get) => $get('icon') ?? 'heroicon-o-tag')
                    ->live(),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('icon')
                    ->label('Ikona')
                    ->icon(fn(?string $state): ?string => $state),

                Tables\Columns\TextColumn::make('name')
                    ->label('Názov')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('planItem.name')
                    ->label('Pilier')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->color(fn ($state) => $state === 'income' ? 'success' : 'danger')
                    ->formatStateUsing(fn ($state) => $state === 'income' ? 'Príjem' : 'Výdavok'),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Farba'),

                Tables\Columns\TextColumn::make('children_count')
                    ->label('Podkategórie')
                    ->counts('children')
                    ->badge()
                    ->color('gray'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('parent_id');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ChildrenRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}