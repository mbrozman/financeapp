<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvestment extends EditRecord
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Tlačidlo pre návrat do detailu
            Actions\ViewAction::make(),

            // Tlačidlo pre odstránenie s vynútenou viditeľnosťou
            Actions\DeleteAction::make()
                ->label('Odstrániť investíciu')
                ->visible(true), // TOTO VYNÚTI ZOBRAZENIE
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}