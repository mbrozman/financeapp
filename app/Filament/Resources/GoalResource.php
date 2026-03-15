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
    protected static ?int $navigationSort = 4;
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

                        Forms\Components\Select::make('account_id')
                            ->label('Previazať s účtom (Automatický progres)')
                            ->relationship('account', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->helperText('Ak vyberiete účet, progres sa bude aktualizovať automaticky podľa jeho zostatku.'),

                        Forms\Components\TextInput::make('current_amount')
                            ->label('Ručný aktuálny stav')
                            ->numeric()
                            ->required(fn(Forms\Get $get) => empty($get('account_id')))
                            ->disabled(fn(Forms\Get $get) => filled($get('account_id')))
                            ->dehydrated()
                            ->default(0)
                            ->prefix('€'),

                        Forms\Components\DatePicker::make('deadline')
                            ->label('Cieľový dátum')
                            ->native(false),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Farba grafu')
                            ->default('#3b82f6'),
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
            'edit' => Pages\EditGoal::route('/{record}/edit'),
        ];
    }
}
