<?php

namespace App\Filament\Resources\InvestmentResource\RelationManagers;

use App\Models\Currency;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class DividendsRelationManager extends RelationManager
{
    protected static string $relationship = 'dividends';
    protected static ?string $title = 'Prijaté Dividendy';
    protected static ?string $modelLabel = 'Dividenda';
    protected static ?string $pluralModelLabel = 'Dividendy';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('payout_date')
                    ->label('Dátum výplaty')
                    ->required()
                    ->default(now()),

                Forms\Components\TextInput::make('amount')
                    ->label('Čistá suma (Net)')
                    ->required()
                    ->numeric()
                    ->minValue(0.0001),

                Forms\Components\Select::make('currency_id')
                    ->label('Mena')
                    ->options(Currency::pluck('code', 'id'))
                    ->default(fn($livewire) => $livewire->getOwnerRecord()->currency_id)
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Forms\Set $set, $state) {
                        $rate = CurrencyService::getLiveRateById($state);
                        $set('exchange_rate', $rate);
                    }),

                Forms\Components\TextInput::make('exchange_rate')
                    ->label('Kurz (voči EUR)')
                    ->required()
                    ->numeric()
                    ->default(fn($livewire) => CurrencyService::getLiveRateById($livewire->getOwnerRecord()->currency_id))
                    ->minValue(0.0001),

                Forms\Components\Toggle::make('add_to_broker_balance')
                    ->label('Pričítať sumu k voľnej hotovosti na brokerskom účte')
                    ->default(true)
                    ->columnSpanFull()
                    ->helperText('Po uložení sa obnos dividendy automaticky pripočíta do hotovosti na účet, ku ktorému patrí táto investícia.'),

                Forms\Components\Textarea::make('notes')
                    ->label('Poznámka')
                    ->columnSpanFull()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('payout_date')
                    ->label('Dátum')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma')
                    ->money(fn($record) => $record->currency->code)
                    ->sortable(),
                Tables\Columns\TextColumn::make('add_to_broker_balance')
                    ->label('Pripísané brokerovi')
                    ->formatStateUsing(fn ($state) => $state ? 'Áno' : 'Nie')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('payout_date', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = $this->getOwnerRecord()->user_id;
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
