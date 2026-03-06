<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MonthlyIncomeResource\Pages;
use App\Models\MonthlyIncome;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MonthlyIncomeResource extends Resource
{
    protected static ?string $model = MonthlyIncome::class;

    // VIZUÁLNE NASTAVENIA
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Financie';
    
    public static function getNavigationLabel(): string { return 'Mesačné príjmy'; }
    public static function getPluralLabel(): string { return 'Mesačné príjmy'; }
    public static function getModelLabel(): string { return 'Mesačný príjem'; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Príjem pre daný mesiac')
                    ->schema([
                        // Výber mesiaca a roku
                        Forms\Components\Select::make('period')
                            ->label('Mesiac')
                            ->options(function() {
                                $options = [];
                                // Vygenerujeme zoznam: 3 mesiace dozadu a 6 dopredu
                                for ($i = -3; $i <= 6; $i++) {
                                    $date = now()->addMonths($i);
                                    $options[$date->format('Y-m')] = $date->translatedFormat('F Y');
                                }
                                return $options;
                            })
                            ->default(now()->format('Y-m'))
                            ->required()
                            ->native(false),

                        // Suma reálnej výplaty
                        Forms\Components\TextInput::make('amount')
                            ->label('Suma výplaty / Príjmu')
                            ->numeric()
                            ->prefix('€')
                            ->required(),

                        Forms\Components\TextInput::make('note')
                            ->label('Poznámka')
                            ->placeholder('napr. Základný plat + bonus'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('period')
                    ->label('Mesiac')
                    ->formatStateUsing(function ($state) {
                        // Prevedie "2025-03" na "Marec 2025"
                        return \Carbon\Carbon::parse($state . '-01')->translatedFormat('F Y');
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma príjmu')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('note')
                    ->label('Poznámka')
                    ->limit(50)
                    ->placeholder('Bez poznámky'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Naposledy zmenené')
                    ->dateTime('d.m.Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('period', 'desc') // Najnovšie mesiace navrchu
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListMonthlyIncomes::route('/'),
            'create' => Pages\CreateMonthlyIncome::route('/create'),
            'edit' => Pages\EditMonthlyIncome::route('/{record}/edit'),
        ];
    }
}