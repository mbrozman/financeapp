<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    public static function getNavigationLabel(): string
    {
        return 'Audit Log';
    }
    public static function getPluralLabel(): string
    {
        return 'Audit Logy';
    }
    public static function getModelLabel(): string
    {
        return 'Záznam';
    }
    protected static ?string $navigationGroup = '🔧 NASTAVENIA';
    protected static ?int $navigationSort = 104;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $label = 'Audit Log';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin() === false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dátum a čas')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('Užívateľ')
                    ->placeholder('Systém'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Akcia')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Modul')
                    ->formatStateUsing(fn($state) => str($state)->afterLast('\\')),

                Tables\Columns\TextColumn::make('properties')
                    ->label('Zmeny')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}
