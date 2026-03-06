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

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

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

                        Forms\Components\TextInput::make('expected_annual_return')
                            ->label('Očakávaný ročný výnos portfólia (%)')
                            ->numeric()
                            ->default(8)
                            ->suffix('%')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Rozdelenie výplaty')
                    ->description('Nastavte si, koľko % z platu má ísť do ktorej oblasti. Súčet musí byť 100%.')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship() // Prepojenie na tabuľku financial_plan_items
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Názov položky')
                                    ->required(),

                                Forms\Components\TextInput::make('percentage')
                                    ->label('Podiel (%)')
                                    ->numeric()
                                    ->suffix('%')
                                    ->required()
                                    ->live(onBlur: true),

                                Forms\Components\Toggle::make('contributes_to_net_worth')
                                    ->label('Buduje majetok?')
                                    ->helperText('Ak zapnete, táto suma sa započíta do modelového rastu majetku.')
                                    ->default(false),

                                Forms\Components\Toggle::make('applies_expected_return')
                                    ->label('Aplikovať zhodnotenie 8%?')
                                    ->helperText('Zapnite len pre akcie/ETF.')
                                    ->default(false),
                            ])
                            ->columns(4)
                            ->defaultItems(3)
                            ->addActionLabel('Pridať ďalší šuflík')
                            // Validácia: Súčet percent musí byť 100
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
                // OPRAVA: Tu definujeme, čo má tabuľka reálne vypísať
                Tables\Columns\TextColumn::make('monthly_income')
                    ->label('Mesačný príjem')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expected_annual_return')
                    ->label('Očakávaný výnos')
                    ->suffix(' %')
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
