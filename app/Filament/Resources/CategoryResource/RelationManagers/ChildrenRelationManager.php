<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Podkategória')
                    ->weight('bold'),

                // Podkategória ukazuje farbu rodiča
                Tables\Columns\ColorColumn::make('parent.color')
                    ->label('Zdedená farba'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Pridať podkategóriu')
                    // Automaticky priradíme ID aktuálneho rodiča
                    ->mutateFormDataUsing(function (array $data) {
                        $data['user_id'] = auth()->id();
                        $data['type'] = 'expense';
                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
