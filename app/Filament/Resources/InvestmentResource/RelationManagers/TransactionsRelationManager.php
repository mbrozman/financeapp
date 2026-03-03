<?php

namespace App\Filament\Resources\InvestmentResource\RelationManagers;

use App\Filament\Resources\InvestmentResource\Pages\ViewInvestment;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    // 1. SILNÁ OPRAVA: Povieme Filamentu, že táto tabuľka NIE JE len na čítanie
    public function isReadOnly(): bool
    {
        return false;
    }

    // 2. NASTAVENIE NÁZVU (Cez statickú premennú, to je najistejšie)
    protected static ?string $title = 'História nákupov a predajov';

    // 3. VYNÚTENIE PRÁV
    protected function canCreate(): bool
    {
        return true;
    }
    protected function canEdit(Model $record): bool
    {
        return true;
    }
    protected function canDelete(Model $record): bool
    {
        return true;
    }


    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        // Tabuľka transakcií sa zobrazí IBA na stránke ViewInvestment
        // Na stránke EditInvestment (Upraviť) vráti false a skryje sa
        return $pageClass === ViewInvestment::class;
    }
public function form(Form $form): Form
{
    // Získame menu investície (vlastník tohto zoznamu)
    $assetCurrency = $this->getOwnerRecord()->currency;
    $symbol = $assetCurrency?->symbol ?? '$';
    $code = $assetCurrency?->code ?? 'USD';

    return $form
        ->schema([
            Forms\Components\Grid::make(2)
                ->schema([
                    Forms\Components\Select::make('type')
                        ->label('Typ pohybu')
                        ->options([
                            'buy' => 'Nákup',
                            'sell' => 'Predaj',
                            'dividend' => 'Dividenda',
                        ])->required()->native(false),

                    Forms\Components\DatePicker::make('transaction_date')
                        ->label('Dátum')->default(now())->required(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Počet kusov')
                        ->numeric()->required()->step(0.00000001),

                    Forms\Components\TextInput::make('price_per_unit')
                        ->label("Cena za kus ({$code})") // DYNAMICKÝ LABEL
                        ->numeric()->required()
                        ->prefix($symbol),

                    Forms\Components\TextInput::make('commission')
                        ->label("Poplatok brokerovi ({$code})") // DYNAMICKÝ LABEL
                        ->numeric()->default(0)
                        ->prefix($symbol),

                    Forms\Components\TextInput::make('exchange_rate')
                        ->label("Menový kurz ({$code} / EUR)") // JASNÉ VYSVETLENIE
                        ->numeric()
                        ->required()
                        ->default(fn () => (float)($assetCurrency?->exchange_rate ?? 1.08))
                        ->helperText("Zadajte, koľko {$code} dostanete za 1 EUR v čase nákupu."),
                ]),
        ]);
}

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')->label('Dátum')->date('d. m. Y'),
                Tables\Columns\TextColumn::make('type')->label('Typ')->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'buy' => 'success',
                        'sell' => 'danger',
                        'dividend' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'buy' => 'Nákup',
                        'sell' => 'Predaj',
                        'dividend' => 'Dividenda',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('quantity')->label('Kusy')->numeric(4),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Celková suma')
                    ->state(function ($record) {
                        // Množstvo * Cena za kus
                        return (float)$record->quantity * (float)$record->price_per_unit;
                    })
                    ->money('USD') // Keďže akcie sú v USD, aj celok dáme v USD
                    ->weight('bold')
                    ->color('gray')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('price_per_unit')->label('Cena/ks')->money('USD'),
                Tables\Columns\TextColumn::make('commission')->label('Poplatok')->money('USD'),
            ])
             ->headerActions([
            Tables\Actions\CreateAction::make()
                ->label('Nový pohyb')
                ->mutateFormDataUsing(function (array $data): array {
                    // TOTO ZABEZPEČÍ KONZISTENCIU S DATABÁZOU
                    $data['user_id'] = auth()->id();
                    $data['currency_id'] = $this->getOwnerRecord()->currency_id; // Použijeme ID meny z Investície
                    return $data;
                })
                ->after(fn () => redirect(request()->header('Referer'))),
        ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
