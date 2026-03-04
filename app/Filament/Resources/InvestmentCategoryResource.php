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
    protected static ?string $navigationGroup = 'Investície';
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
                        // Vizuálny indikátor aktivity
                        Tables\Columns\ToggleColumn::make('is_active')
                            ->label('Aktívny'),

                        // Indikátor zmazania (Soft delete)
                        Tables\Columns\TextColumn::make('deleted_at')
                            ->label('Stav')
                            ->dateTime()
                            ->placeholder('Aktívny záznam')
                            ->color('danger')
                            ->toggleable(isToggledHiddenByDefault: true),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('icon')
                            ->label('Ikona (Heroicon názov)')
                            ->placeholder('heroicon-o-chart-bar')
                            ->default('heroicon-o-chart-bar'),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Farba pre grafy')
                            ->default('#3b82f6'),
                    ])->columns(2),
            ])->filters([
                // Filter na zobrazenie zmazaných záznamov
                Tables\Filters\TrashedFilter::make(),
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
