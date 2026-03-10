<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyIncomeResource\Pages;
use App\Filament\Resources\MonthlyIncomeResource\RelationManagers;
use App\Models\MonthlyIncome;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MonthlyIncomeResource extends Resource
{
    protected static ?string $model = MonthlyIncome::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Zadanie mesačnej výplaty')
                    ->description('Túto sumu systém použije na výpočet limitov pre tvoje finančné piliere.')
                    ->schema([
                        // 1. VÝBER MESIACA (Ukladá napr. 2025-03)
                        Forms\Components\Select::make('period')
                            ->label('Mesiac')
                            ->options(function () {
                                $options = [];
                                for ($i = -3; $i <= 6; $i++) {
                                    $date = now()->addMonths($i);
                                    $options[$date->format('Y-m')] = $date->translatedFormat('F Y');
                                }
                                return $options;
                            })
                            ->default(now()->format('Y-m'))
                            ->required()
                            ->native(false),

                        // 2. SUMA VÝPLATY
                        Forms\Components\TextInput::make('amount')
                            ->label('Suma, ktorá ti prišla na účet')
                            ->numeric()
                            ->prefix('€')
                            ->required(),

                        Forms\Components\TextInput::make('note')
                            ->label('Poznámka')
                            ->placeholder('napr. Výplata + odmeny'),
                    ])->columns(3),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Split::make([
                    Tables\Columns\TextColumn::make('period')
                        ->label('Mesiac')
                        ->formatStateUsing(fn($state) => \Carbon\Carbon::parse($state . '-01')->translatedFormat('F Y'))
                        ->weight('bold')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('amount')
                        ->label('Zadané príjmy')
                        ->money('EUR')
                        ->color('success')
                        ->weight('bold'),
                ]),
                Tables\Columns\Layout\Panel::make([
                    Tables\Columns\ViewColumn::make('details')
                        ->view('filament.tables.columns.monthly-income-transactions')
                ])->collapsible(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    // Zaradenie do menu
    protected static ?string $navigationGroup = 'Financie';
    protected static ?string $navigationLabel = 'Mesačné príjmy';
    protected static bool $shouldRegisterNavigation = false;

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMonthlyIncomes::route('/'),
            'create' => Pages\CreateMonthlyIncome::route('/create'),
            'edit' => Pages\EditMonthlyIncome::route('/{record}/edit'),
        ];
    }
}
