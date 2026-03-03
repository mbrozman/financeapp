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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    public static function getNavigationLabel(): string
    {
        return 'Kategórie';
    }
    public static function getPluralLabel(): string
    {
        return 'Kategórie';
    }
    public static function getModelLabel(): string
    {
        return 'Kategória';
    }
    protected static ?string $navigationGroup = 'Nastavenia';
    protected static ?string $navigationIcon = 'heroicon-o-tag';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detaily kategórie')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Názov kategórie')
                            ->required()
                            ->placeholder('napr. Potraviny'),

                        Forms\Components\Select::make('type')
                            ->label('Typ pohybu')
                            ->options([
                                'income' => 'Príjem',
                                'expense' => 'Výdavok',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('parent_id')
                            ->label('Nadradená kategória')
                            ->relationship('parent', 'name') // Vyberáme z už existujúcich kategórií
                            ->searchable()
                            ->preload()
                            ->helperText('Nechajte prázdne, ak ide o hlavnú kategóriu'),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Farba pre grafy')
                            ->default('#3b82f6'), // Predvolená modrá
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Názov')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Nadradená kategória')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Hlavná kategória'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Typ')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'income' => 'success',
                        'expense' => 'danger',
                    }),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Farba'),
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
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
