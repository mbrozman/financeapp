<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrokerResource\Pages;
use App\Models\Account; // PRIDAJ TENTO IMPORT
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BrokerResource extends Resource
{
    // TU ZMEŇ Broker::class na Account::class
    protected static ?string $model = Account::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = '🔧 NASTAVENIA';
    protected static ?int $navigationSort = 1;
    
    public static function getNavigationLabel(): string { return 'Brokeri'; }
    public static function getModelLabel(): string { return 'Broker'; }
    public static function getPluralModelLabel(): string { return 'Brokeri'; }

    // FILTRÁCIA: Tu povieme, že v tejto sekcii chceme vidieť IBA investičné účty
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->where('type', 'investment');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Detaily brokera')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Názov brokera (napr. XTB, IBKR)')
                    ->required(),
                
                // Typ nastavíme automaticky ako skrytý, aby užívateľ nemohol vybrať iný
                Forms\Components\Hidden::make('type')->default('investment'),

                Forms\Components\Select::make('currency_id')
                    ->label('Základná mena brokera')
                    ->relationship('currency', 'code')
                    ->required(),

                Forms\Components\TextInput::make('balance')
                    ->label('Voľná hotovosť u brokera (Cash)')
                    ->numeric()
                    ->default(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label('Broker')
                ->weight('bold')
                ->searchable(),
            Tables\Columns\ToggleColumn::make('is_active')
                ->label('Aktívny'),
            Tables\Columns\TextColumn::make('deleted_at')
                ->label('Stav')
                ->dateTime()
                ->placeholder('Aktívny záznam')
                ->color('danger')
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('balance')
                ->label('Hotovosť na účte')
                ->money(fn ($record) => $record->currency->code),
            Tables\Columns\TextColumn::make('currency.code')
                ->label('Mena'),
        ])->filters([
            // Filter na zobrazenie zmazaných záznamov
            Tables\Filters\TrashedFilter::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrokers::route('/'),
            'create' => Pages\CreateBroker::route('/create'),
            'edit' => Pages\EditBroker::route('/{record}/edit'),
        ];
    }
}