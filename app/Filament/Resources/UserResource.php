<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    public static function getNavigationLabel(): string
    {
        return 'Užívatelia';
    }
    public static function getPluralLabel(): string
    {
        return 'Užívatelia';
    }
    public static function getModelLabel(): string
    {
        return 'Užívateľ';
    }
    protected static ?string $navigationGroup = 'Systém';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function canViewAny(): bool
    {
        // Iba užívateľ, ktorý má v DB is_admin = true, vidí zoznam užívateľov
        return auth()->user()->is_admin;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),

                // Toggle na okamžitú zmenu aktivity bez otvárania formulára
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Aktívny'),

                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Stav konta'),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
