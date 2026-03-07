<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
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
            Forms\Components\Section::make('Nastavenie kategórie')->schema([
                Forms\Components\TextInput::make('name')->label('Názov')->required(),
                
                Forms\Components\Select::make('parent_id')
                    ->label('Nadradená kategória')
                    ->relationship('parent', 'name', fn(Builder $query) => $query->whereNull('parent_id'))
                    ->placeholder('Hlavná kategória')
                    ->live(),

                Forms\Components\Select::make('financial_plan_item_id')
                    ->label('Finančný pilier')
                    ->relationship('planItem', 'name')
                    ->visible(fn (Forms\Get $get) => !$get('parent_id'))
                    ->required(fn (Forms\Get $get) => !$get('parent_id')),

                Forms\Components\ColorPicker::make('color')
                    ->label('Farba')
                    ->visible(fn (Forms\Get $get) => !$get('parent_id')),
                
                Forms\Components\Hidden::make('type')->default('expense'),
            ])->columns(2)
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Názov')
                    ->formatStateUsing(fn ($state, $record) => $record?->parent_id ? "↳ {$state}" : $state)
                    ->weight(fn ($record) => $record?->parent_id ? 'normal' : 'bold')
                    ->color(fn ($record) => $record?->parent_id ? 'gray' : null)
                    ->searchable(),

                Tables\Columns\TextColumn::make('planItem.name')
                    ->label('Pilier')
                    ->badge()
                    ->state(fn ($record) => $record?->parent_id ? null : $record?->planItem?->name),

                Tables\Columns\ColorColumn::make('effective_color')->label('Farba'),
            ])
            ->groups([
                Group::make('group_name')
                    ->label('')
                    ->collapsible()->titlePrefixedWithLabel(false),
                    
            ])
            ->defaultGroup('group_name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        // 1. Základný dotaz
        $query = parent::getEloquentQuery();

        // 2. Ručne kvalifikujeme dotaz, aby sme sa vyhli konfliktom mien
        return $query
            ->leftJoin('categories as parents', 'categories.parent_id', '=', 'parents.id')
            ->select(
                'categories.*', 
                DB::raw('COALESCE(parents.name, categories.name) as group_name')
            )
            ->orderBy('group_name')
            ->orderByRaw('categories.parent_id IS NOT NULL ASC');
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