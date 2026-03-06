<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetRuleResource\Pages;
use App\Filament\Resources\BudgetRuleResource\RelationManagers;
use App\Models\BudgetRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BudgetRuleResource extends Resource
{
    protected static ?string $model = BudgetRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Pravidlo rozpočtu')
                    ->description('Definujte trvalý mesačný limit pre kategóriu.')
                    ->schema([
                        // Výber kategórie (iba výdavky)
                        Forms\Components\Select::make('category_id')
                            ->label('Kategória výdavkov')
                            ->relationship('category', 'name', fn($query) => $query->where('type', 'expense'))
                            ->required()
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                return $rule->where('user_id', auth()->id());
                            })
                            ->searchable()
                            ->preload(),

                        // Priradenie k Finančnému pilieru (šuflíku)
                        Forms\Components\Select::make('financial_plan_item_id')
                            ->label('Patrí pod pilier (šuflík)')
                            ->relationship('planItem', 'name')
                            ->required(),

                        // Mesačný limit
                        Forms\Components\TextInput::make('limit_amount')
                            ->label('Mesačný limit (€)')
                            ->numeric()
                            ->prefix('€')
                            ->required(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategória')
                    ->weight('bold')
                    ->sortable(),

                Tables\Columns\TextColumn::make('planItem.name')
                    ->label('Finančný pilier')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('limit_amount')
                    ->label('Mesačný limit')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListBudgetRules::route('/'),
            'create' => Pages\CreateBudgetRule::route('/create'),
            'edit' => Pages\EditBudgetRule::route('/{record}/edit'),
        ];
    }
}
