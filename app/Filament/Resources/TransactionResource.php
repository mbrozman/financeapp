<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Filament\Resources\TransactionResource\RelationManagers;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\IconColumn;


class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Základné údaje o platbe')
                    ->schema([
                        Forms\Components\Select::make('account_id')
                            ->label('Účet')
                            ->relationship('account', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('category_id')
                            ->label('Kategória')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('amount')
                            ->label('Suma')
                            ->numeric()
                            ->required()
                            // Oficiálne metódy: prefixIcon a prefixIconColor
                            ->prefixIcon(fn($get) => $get('type') === 'expense' ? 'heroicon-m-minus-circle' : 'heroicon-m-plus-circle')
                            ->prefixIconColor(fn($get) => match ($get('type')) {
                                'expense' => 'danger',
                                'income' => 'success',
                                'transfer' => 'gray',
                                default => 'gray',
                            })
                            ->helperText(fn($get) => $get('type') === 'expense'
                                ? 'Bude uložené ako výdavok (mínus sa doplní automaticky).'
                                : 'Bude uložené ako príjem.'),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Dátum')
                            ->default(now())
                            ->required(),

                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'income' => 'Príjem',
                                'expense' => 'Výdavok',
                                'transfer' => 'Prevod',
                            ])
                            ->required()
                            ->live()
                            ->native(false),

                        Forms\Components\TextInput::make('description')
                            ->label('Poznámka')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        FileUpload::make('attachment')
                            ->label('Bloček / Faktúra')
                            ->directory('transaction-attachments') // Súbory sa uložia do storage/app/public/transaction-attachments
                            ->image() // Povolí náhľady, ak ide o obrázok
                            ->imageEditor() // Umožní užívateľovi orezať fotku bločku priamo v prehliadači!
                            ->openable() // Pridá tlačidlo na otvorenie súboru
                            ->downloadable() // Pridá tlačidlo na stiahnutie
                            ->maxSize(5120) // Limit 5 MB
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table

            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Dátum')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('account.name')
                    ->label('Účet'),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategória')
                    ->placeholder('Bez kategórie'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Suma')
                    ->money(fn($record) => $record->account->currency->code)
                    ->color(fn($record) => match ($record->type) {
                        'income' => 'success',
                        'expense' => 'danger',
                        'transfer' => 'gray', // Prevody budú sivé
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                IconColumn::make('attachment')
    ->label('Bloček')
    ->icon('heroicon-o-paper-clip') // Ikona spinky
    ->color('info')
    ->boolean() // Ak je v stĺpci cesta k súboru, zobrazí ikonu. Ak je null, nezobrazí nič.
    ->toggleable(isToggledHiddenByDefault: true), // Umožní stĺpec skryť/zobraziť v nastaveniach tabuľky
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),

                    // NAŠA NOVÁ AKCIA
                    Tables\Actions\BulkAction::make('export_pdf')
                        ->label('Exportovať do PDF')
                        ->icon('heroicon-o-document-arrow-down')
                        ->action(function (Collection $records) {
                            // $records obsahuje všetky označené riadky
                            $pdf = Pdf::loadView('reports.transactions-pdf', [
                                'transactions' => $records,
                            ]);

                            // Vrátime PDF ako download
                            return response()->streamDownload(function () use ($pdf) {
                                echo $pdf->output();
                            }, 'vypis-transakcii-' . now()->format('d-m-Y') . '.pdf');
                        }),
                ]),
            ])
            ->defaultSort('transaction_date', 'desc'); // Najnovšie transakcie hore

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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    // app/Filament/Resources/TransactionResource.php

    public static function getNavigationLabel(): string
    {
        return 'Transakcie'; // Názov v bočnom menu
    }

    public static function getPluralLabel(): string
    {
        return 'Transakcie'; // Názov v nadpise zoznamu
    }

    public static function getModelLabel(): string
    {
        return 'Transakcia'; // Názov pri vytváraní "New Transaction" -> "Nová transakcia"
    }

    protected static ?string $navigationGroup = 'Financie'; // Zoskupenie v menu
}
