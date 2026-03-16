<?php

namespace App\Filament\Resources\InvestmentResource\RelationManagers;

use App\Filament\Resources\InvestmentResource\Pages\ViewInvestment;
use App\Services\CurrencyService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Brick\Math\BigDecimal; // PRIDANÉ
use Brick\Math\RoundingMode; // PRIDANÉ
use App\Enums\TransactionType;


class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';
    protected static ?string $title = 'História nákupov a predajov';

    public function isReadOnly(): bool
    {
        return false;
    }

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
        return $pageClass === ViewInvestment::class;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Typ pohybu')
                            ->options(TransactionType::class)
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('currency_id')
                            ->label('Mena transakcie')
                            ->relationship('currency', 'code')
                            ->required()
                            ->live()
                            ->default(fn() => $this->getOwnerRecord()->currency_id),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Dátum')
                            ->default(now())
                            ->required(),
                    ]),

                Forms\Components\Grid::make(3)
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Počet kusov')
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::calculateCommission($set, $get))
                            ->step(0.00000001)
                            ->rules([
                                fn(Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('type') === \App\Enums\TransactionType::SELL->value) {
                                        $currentQty = (float) $this->getOwnerRecord()->total_quantity;
                                        $originalQty = (float) ($this->getMountedTableActionRecord()?->quantity ?? 0);
                                        $availableQty = $currentQty + $originalQty;

                                        if ((float)$value > $availableQty) {
                                            $fail("Nemôžete predať viac kusov, než vlastníte. Aktuálne k dispozícii: {$availableQty} ks.");
                                        }
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('price_per_unit')
                            ->label(fn(Forms\Get $get) => "Cena za kus (" . (\App\Models\Currency::find($get('currency_id'))?->code ?? 'USD') . ")")
                            ->numeric()
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::calculateCommission($set, $get))
                            ->prefix(fn(Forms\Get $get) => \App\Models\Currency::find($get('currency_id'))?->symbol ?? '$'),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('commission_percent')
                                    ->label('Poplatok v %')
                                    ->numeric()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn($state, Forms\Set $set, Forms\Get $get) => self::calculateCommission($set, $get))
                                    ->suffix('%'),
                                Forms\Components\TextInput::make('commission')
                                    ->label(fn(Forms\Get $get) => "Poplatok (" . (\App\Models\Currency::find($get('currency_id'))?->code ?? 'USD') . ")")
                                    ->numeric()
                                    ->default(0)
                                    ->prefix(fn(Forms\Get $get) => \App\Models\Currency::find($get('currency_id'))?->symbol ?? '$'),
                            ])->columnSpan(1),
                    ]),

                Forms\Components\TextInput::make('exchange_rate')
                    ->label(fn(Forms\Get $get) => "Kurz (1 " . (\App\Models\Currency::find($get('currency_id'))?->code ?? 'USD') . " = X EUR)")
                    ->numeric()
                    ->required()
                    ->default(fn(Forms\Get $get) => CurrencyService::getLiveRateById($get('currency_id')))
                    ->helperText(fn(Forms\Get $get) => "Zadajte kurz pre menu " . (\App\Models\Currency::find($get('currency_id'))?->code ?? 'USD') . " voči EUR."),
            ]);
    }

    protected static function calculateCommission(Forms\Set $set, Forms\Get $get): void
    {
        $percent = (float) ($get('commission_percent') ?? 0);
        if ($percent <= 0) return;

        $qty = (float) ($get('quantity') ?? 0);
        $price = (float) ($get('price_per_unit') ?? 0);

        if ($qty > 0 && $price > 0) {
            $absCommission = ($qty * $price) * ($percent / 100);
            $set('commission', number_format($absCommission, 2, '.', ''));
        }
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('transaction_date')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')->label('Dátum')->date('d. m. Y'),

                Tables\Columns\TextColumn::make('type')->label('Typ')->badge()

                    ->formatStateUsing(fn($state) => match ($state) {
                        'buy' => 'Nákup',
                        'sell' => 'Predaj',
                        'dividend' => 'Dividenda',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Ks')
                    ->numeric(4),

                // FIX: CELKOVÁ SUMA CEZ BIGDECIMAL
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Celková suma')
                    ->alignEnd()
                    ->state(function ($record) {
                        // Presný výpočet: Ks * Cena
                        $total = BigDecimal::of($record->quantity)
                            ->multipliedBy($record->price_per_unit);

                        return (string) $total;
                    })
                    // Peniaze formátujeme až pri zobrazení
                    ->formatStateUsing(
                        fn($state, $record) =>
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '$')
                    )
                    ->weight('bold')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('price_per_unit')
                    ->label('Cena/ks')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '$')
                    ),

                Tables\Columns\TextColumn::make('commission')
                    ->label('Poplatok')
                    ->formatStateUsing(
                        fn($state, $record) =>
                        number_format((float)$state, 2, ',', ' ') . ' ' . ($record->currency?->symbol ?? '$')
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nový pohyb')
                    ->icon('heroicon-m-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        return $data;
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
