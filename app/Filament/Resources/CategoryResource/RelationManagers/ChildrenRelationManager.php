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
                Forms\Components\TextInput::make('monthly_limit')
                    ->label('Mesačný limit (€)')
                    ->numeric()
                    ->prefix('€'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Názov')
                    ->weight('bold'),
                
                Tables\Columns\ColorColumn::make('effective_color')
                    ->label('Farba (automaticky)')
                    ->tooltip('Farba je odvodená od hlavnej kategórie'),

                Tables\Columns\TextColumn::make('monthly_limit')
                    ->label('Limit')
                    ->money('EUR'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $parent = $this->getOwnerRecord();
                        $data['user_id'] = $parent->user_id;
                        $data['type'] = $parent->type;
                        $data['financial_plan_item_id'] = $parent->financial_plan_item_id;
                        // Color necháme null, aby sa uplatnila logika z modelu effectiveColor
                        return $data;
                    }),
                Tables\Actions\AttachAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
