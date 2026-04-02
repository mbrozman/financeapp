<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GoalResource\Pages;
use App\Filament\Resources\GoalResource\RelationManagers;
use App\Models\Goal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\ViewColumn;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class GoalResource extends Resource
{
    protected static ?string $model = Goal::class;
    public static function getNavigationLabel(): string
    {
        return 'Ciele';
    }
    public static function getPluralLabel(): string
    {
        return 'Ciele';
    }
    public static function getModelLabel(): string
    {
        return 'Cieľ';
    }
    protected static ?string $navigationGroup = '💸 OPERÁCIE';
    protected static ?int $navigationSort = 22;
    protected static ?string $navigationIcon = 'heroicon-o-flag';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Nastavenie cieľa')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Názov cieľa')
                            ->required()
                            ->placeholder('napr. Finančná rezerva'),

                        Forms\Components\Select::make('type')
                            ->label('Typ')
                            ->options([
                                'saving' => 'Sporenie / Cieľ',
                                'debt' => 'Dlh / Hypotéka',
                            ])
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('target_amount')
                            ->label('Cieľová suma')
                            ->numeric()
                            ->required()
                            ->prefix('€'),

                        Forms\Components\Select::make('accounts')
                            ->label('Previazať s účtami (Hotovosť / Sporenie)')
                            ->relationship('accounts', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Zostatky z týchto účtov sa sčítajú do celkového stavu (vhodné pre bežné účty a hotovosť).'),

                        Forms\Components\Select::make('investments')
                            ->label('Zahrnúť konkrétne investície (ETF)')
                            ->relationship('investments', 'ticker')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Hodnota týchto ETF sa pripočíta do celkového stavu rezervy (vhodné pre selektívne pridávanie majetku).'),

                        Forms\Components\TextInput::make('current_amount')
                            ->label('Ručný aktuálny stav')
                            ->numeric()
                            ->required(fn(Forms\Get $get) => empty($get('accounts')))
                            ->disabled(fn(Forms\Get $get) => !empty($get('accounts')))
                            ->dehydrated()
                            ->default(0)
                            ->prefix('€'),

                        Forms\Components\DatePicker::make('deadline')
                            ->label('Cieľový dátum')
                            ->native(false),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Farba grafu')
                            ->default('#3b82f6'),

                        Forms\Components\Toggle::make('is_reserve')
                            ->label('Hlavná finančná rezerva')
                            ->helperText('Tento cieľ bude zobrazený na hlavnom Dashboarde ako tvoj bezpečnostný vankúš.')
                            ->default(false)
                            ->live()
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cieľ')
                    ->sortable()
                    ->searchable(),

                // TOTO JE OPRAVA: Použijeme náš vlastný View
                ViewColumn::make('progress')
                    ->label('Progres')
                    ->view('filament.tables.columns.progress-bar'), // Cesta k súboru, ktorý sme vytvorili

                Tables\Columns\TextColumn::make('current_amount')
                    ->label('Nasporené')
                    ->money('EUR'),

                Tables\Columns\TextColumn::make('target_amount')
                    ->label('Cieľ')
                    ->money('EUR'),

                Tables\Columns\TextColumn::make('deadline')
                    ->label('Termín')
                    ->date()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Prehľad'),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListGoals::route('/'),
            'create' => Pages\CreateGoal::route('/create'),
            'view' => Pages\ViewGoal::route('/{record}'),
            'edit' => Pages\EditGoal::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Prehľad cieľa')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('Názov cieľa')
                            ->weight('bold')
                            ->size('lg'),
                        Infolists\Components\TextEntry::make('type')
                            ->label('Typ')
                            ->badge()
                            ->color(fn($state) => $state === 'saving' ? 'success' : 'danger'),
                        Infolists\Components\TextEntry::make('target_amount')
                            ->label('Cieľová suma')
                            ->money('EUR'),
                        Infolists\Components\TextEntry::make('current_amount')
                            ->label('Aktuálne nasporené')
                            ->money('EUR')
                            ->color('success')
                            ->weight('black'),
                        Infolists\Components\ViewEntry::make('progress_view')
                            ->label('Progres')
                            ->view('filament.tables.columns.progress-bar')
                            ->state(fn($record) => $record->progress),
                        Infolists\Components\TextEntry::make('deadline')
                            ->label('Termín')
                            ->date()
                            ->placeholder('Bez termínu'),
                    ])->columns(3),

                Infolists\Components\Section::make('Rozpis Majetku')
                    ->schema([
                        // PODSEKCIA: ÚČTY (HOTOVOSŤ)
                        Infolists\Components\RepeatableEntry::make('accounts')
                            ->label('Priradené účty (Hotovosť / Sporenie)')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')->label('Názov účtu'),
                                Infolists\Components\TextEntry::make('type')->label('Typ')->badge(),
                                Infolists\Components\TextEntry::make('balance')
                                    ->label('Príspevok do cieľa')
                                    ->state(function($record) {
                                        $eur = \App\Services\CurrencyService::convertToEur($record->balance ?? 0, $record->currency_id);
                                        return number_format($eur, 2, ',', ' ') . ' €';
                                    })
                                    ->weight('bold'),
                            ])
                            ->columns(3)
                            ->grid(1)
                            ->visible(fn($record) => $record->accounts()->exists()),

                        // PODSEKCIA: INVESTÍCIE (ETF)
                        Infolists\Components\RepeatableEntry::make('investments')
                            ->label('Priradené investície (ETF / Akcie)')
                            ->schema([
                                Infolists\Components\TextEntry::make('ticker')->label('Symbol')->badge()->color('warning'),
                                Infolists\Components\TextEntry::make('name')->label('Názov aktíva'),
                                Infolists\Components\TextEntry::make('current_market_value_eur')
                                    ->label('Trhová hodnota (EUR)')
                                    ->state(fn($record) => number_format($record->current_market_value_eur, 2, ',', ' ') . ' €')
                                    ->weight('bold'),
                            ])
                            ->columns(3)
                            ->grid(1)
                            ->visible(fn($record) => $record->investments()->exists()),
                    ])
            ]);
    }
}
