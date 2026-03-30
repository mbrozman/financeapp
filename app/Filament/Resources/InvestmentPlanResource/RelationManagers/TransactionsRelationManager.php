<?php

namespace App\Filament\Resources\InvestmentPlanResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('transaction_date')
                    ->label('Dátum transakcie')
                    ->required()
                    ->default(now()),
                Forms\Components\TextInput::make('quantity')
                    ->label('Množstvo (ks)')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('price_per_unit')
                    ->label('Cena za 1 ks')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('commission')
                    ->label('Poplatok')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('notes')
                    ->label('Poznámka')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_date')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Dátum')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Množstvo')
                    ->numeric(8),
                Tables\Columns\TextColumn::make('price_per_unit')
                    ->label('Cena/ks')
                    ->money(fn ($record) => $record->currency?->code ?? 'USD'),
                Tables\Columns\TextColumn::make('total_amount_eur')
                    ->label('Suma (EUR)')
                    ->state(function ($record) {
                        // (Qty * Price) / ExchangeRate
                        $amountBase = \Brick\Math\BigDecimal::of($record->quantity)
                            ->multipliedBy($record->price_per_unit);
                        
                        return \App\Services\CurrencyService::convertToEur(
                            (string) $amountBase,
                            $record->currency_id,
                            $record->exchange_rate
                        );
                    })
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Poznámka')
                    ->limit(30),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
