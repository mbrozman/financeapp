<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentPlanResource\Pages;
use App\Filament\Resources\InvestmentPlanResource\RelationManagers;
use App\Models\InvestmentPlan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InvestmentPlanResource extends Resource
{
    protected static ?string $model = InvestmentPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationGroup = '📈 INVESTÍCIE';
    protected static ?string $navigationLabel = 'Investičné plány';
    protected static ?string $pluralLabel = 'Investičné plány';
    protected static ?string $modelLabel = 'Investičný plán';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Základné nastavenia')
                    ->schema([
                        Forms\Components\Select::make('investment_id')
                            ->label('Investícia (ETF/Akcia)')
                            ->relationship('investment', 'ticker', fn (Builder $query) => $query->where('user_id', auth()->id()))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->ticker} - {$record->name}")
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('account_id')
                            ->label('Zdrojový účet (Hotovosť)')
                            ->relationship('account', 'name', fn (Builder $query) => $query->where('user_id', auth()->id()))
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Detaily nákupu')
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('Suma nákupu')
                            ->numeric()
                            ->required(),
                        Forms\Components\Select::make('currency_id')
                            ->label('Mena')
                            ->relationship('currency', 'code')
                            ->default(fn() => \App\Models\Currency::where('code', 'EUR')->first()?->id)
                            ->required(),
                        Forms\Components\Select::make('frequency')
                            ->label('Frekvencia')
                            ->options([
                                'daily' => 'Denne',
                                'weekly' => 'Týždenne',
                                'monthly' => 'Mesačne',
                            ])
                            ->required(),
                        Forms\Components\DatePicker::make('next_run_date')
                            ->label('Nasledujúci nákup')
                            ->default(now()->addDay())
                            ->required(),
                    ])->columns(2),

                Forms\Components\Toggle::make('is_active')
                    ->label('Plán je aktívny')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('investment.ticker')
                    ->label('Symbol')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('investment.name')
                    ->label('Názov')
                    ->limit(20)
                    ->sortable(),
                Tables\Columns\TextColumn::make('account.name')
                    ->label('Účet')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma')
                    ->money(fn ($record) => $record->currency?->code ?? 'EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Frekvencia')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'daily' => 'Denne',
                        'weekly' => 'Týždenne',
                        'monthly' => 'Mesačne',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('next_run_date')
                    ->label('Nasledujúci nákup')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktívny')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('frequency')
                    ->label('Frekvencia')
                    ->options([
                        'daily' => 'Denne',
                        'weekly' => 'Týždenne',
                        'monthly' => 'Mesačne',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Len aktívne'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageInvestmentPlans::route('/'),
        ];
    }
}
