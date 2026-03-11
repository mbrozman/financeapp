<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetResource\Pages;
use App\Models\Budget;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder; // DÔLEŽITÝ IMPORT

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationGroup = 'Financie';

    public static function getNavigationLabel(): string
    {
        return 'Pravidlá rozpočtov';
    }
    public static function getPluralLabel(): string
    {
        return 'Pravidlá rozpočtov';
    }
    public static function getModelLabel(): string
    {
        return 'Pravidlo';
    }

    /**
     * FORMULÁR: Tu definuješ trvalé pravidlo (napr. Strava = 400€)
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nastavenie rozpočtového pravidla')
                    ->description('Tento limit sa bude automaticky aplikovať každý mesiac od zvoleného dátumu.')
                    ->schema([

                        // 1. VÝBER KATEGÓRIE (Podkategórie zoskupené podľa hlavnej)
                        Forms\Components\Select::make('category_id')
                            ->label('Kategória výdavkov')
                            ->options(function () {
                                return \App\Models\Category::whereNotNull('parent_id')
                                    ->where('type', 'expense')
                                    ->with('parent')
                                    ->get()
                                    ->groupBy('parent.name')
                                    ->map(fn($categories) => $categories->pluck('name', 'id'))
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),

                        // 2. VÝBER PILIERA (Z tvojho finančného plánu)
                        Forms\Components\Select::make('financial_plan_item_id')
                            ->label('Priradiť k pilieru (Šuflíku)')
                            ->relationship('planItem', 'name')
                            ->required()
                            ->preload(),

                        // 3. SUMA LIMITU
                        Forms\Components\TextInput::make('limit_amount')
                            ->label('Mesačná suma (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required(),

                        // 4. PLATNOSŤ OD
                        Forms\Components\DatePicker::make('valid_from')
                            ->label('Platí od mesiaca')
                            ->default(now()->startOfMonth())
                            ->native(false)
                            ->displayFormat('d. m. Y')
                            ->required(),

                    ])->columns(2),
            ]);
    }

    /**
     * TABUĽKA: Zoznam tvojich nastavených pravidiel
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')->label('Kategória')->weight('bold'),
                Tables\Columns\TextColumn::make('limit_amount')->label('Mesačný limit')->money('EUR'),
                Tables\Columns\TextColumn::make('valid_from')->label('Platí od')->date('m / Y'),
            ])
            // TOTO ZABEZPEČÍ ZOSKUPOVANIE PODĽA PILIEROV AJ V TABUĽKE
            ->groups([
                Tables\Grouping\Group::make('planItem.name')->label('Finančný pilier'),
            ])
            ->defaultGroup('planItem.name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
            'edit' => Pages\EditBudget::route('/{record}/edit'),
        ];
    }

    // Eager load vzťahov, aby bola tabuľka rýchla
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['category', 'planItem']);
    }
}
