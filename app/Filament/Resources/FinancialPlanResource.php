<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FinancialPlanResource\Pages;
use App\Filament\Resources\FinancialPlanResource\RelationManagers;
use App\Models\FinancialPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FinancialPlanResource extends Resource
{
    protected static ?string $model = FinancialPlan::class;
    public static function getNavigationLabel(): string
    {
        return 'Môj finančný plán';
    }

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationGroup = '🎯 STRATÉGIA';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Hlavný mesačný plán')
                    ->schema([
                        Forms\Components\TextInput::make('monthly_income')
                            ->label('Mesačný čistý príjem (Plat)')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('reserve_target')
                            ->label('Cieľová suma rezervy')
                            ->helperText('Celková suma, ktorú si chcete v kategóriách rezervy a hotovosti našetriť.')
                            ->numeric()
                            ->prefix('€')
                            ->default(0)
                            ->required()
                            ->live(onBlur: true),
                    ])->columns(2),

                Forms\Components\Section::make('Rozdelenie výplaty')
                    ->description('Nastavte si, koľko % z platu má ísť do ktorej oblasti. Súčet musí byť 100%.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship() // Prepojenie na tabuľku financial_plan_items
                            ->schema([
                                Forms\Components\Placeholder::make('item_index')
                                    ->label('')
                                    ->content(function ($get, $state) {
                                        return null; 
                                    })
                                    ->visible(false),

                                Forms\Components\TextInput::make('name')
                                    ->label('Názov položky')
                                    ->placeholder('napr. Rezerva, Investície...')
                                    ->required(),

                                Forms\Components\TextInput::make('percentage')
                                    ->label('Podiel (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required()
                                    ->live(onBlur: true),

                                Forms\Components\ViewField::make('color')
                                    ->label('Základná farba')
                                    ->view('filament.forms.components.pillar-color-picker')
                                    ->required(),

                                Forms\Components\Toggle::make('contributes_to_net_worth')
                                    ->label('Buduje majetok?')
                                    ->helperText('Ak zapnete, táto suma sa započíta do modelového rastu majetku.')
                                    ->default(false),

                                Forms\Components\Select::make('goal_id')
                                    ->label('Priradiť k cieľu')
                                    ->relationship('goal', 'name')
                                    ->placeholder('Vyber cieľ (voliteľné)')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->helperText('Prepojte tento šuflík s konkrétnym majetkovým cieľom (napr. Rezerva).'),

                                Forms\Components\Placeholder::make('goal_info')
                                    ->label('')
                                    ->content(function ($get) {
                                        $goalId = $get('goal_id');
                                        if (!$goalId) return null;

                                        $goal = \App\Models\Goal::find($goalId);
                                        if (!$goal) return null;

                                        $income = (float) ($get('../../monthly_income') ?? 0);
                                        $pct = (float) ($get('percentage') ?? 0);
                                        $monthly = $income * ($pct / 100);
                                        $target = (float) $goal->target_amount;
                                        
                                        $months = $monthly > 0 ? round($target / $monthly, 1) : 0;
                                        
                                        return "🎯 Cieľ: {$goal->name} ({$target} €) | Mesačný vklad: {$monthly} € → cca {$months} mesiacov";
                                    })
                                    ->columnSpanFull()
                                    ->visible(fn ($get) => (bool) $get('goal_id')),
                            ])
                            ->columns(5) // Adjusted back from 6 after removing ROI toggle
                            ->defaultItems(3)
                            ->addActionLabel('Pridať ďalší šuflík')
                            ->rules([
                                fn() => function (string $attribute, $value, \Closure $fail) {
                                    $total = collect($value)->sum('percentage');
                                    if ($total != 100) {
                                        $fail("Celkový súčet percent musí byť presne 100. Aktuálne: {$total}%");
                                    }
                                },
                            ]),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('monthly_income')
                    ->label('Mesačný príjem')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items_count')
                    ->label('Počet šuflíkov')
                    ->counts('items'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Posledná zmena')
                    ->dateTime('d.m.Y H:i'),
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
            'index' => Pages\ListFinancialPlans::route('/'),
            'create' => Pages\CreateFinancialPlan::route('/create'),
            'edit' => Pages\EditFinancialPlan::route('/{record}/edit'),
        ];
    }
}
