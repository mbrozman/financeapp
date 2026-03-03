<?php

namespace App\Filament\Resources\InvestmentCategoryResource\Pages;

use App\Filament\Resources\InvestmentCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvestmentCategory extends EditRecord
{
    protected static string $resource = InvestmentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
