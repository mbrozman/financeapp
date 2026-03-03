<?php

namespace App\Filament\Resources\InvestmentCategoryResource\Pages;

use App\Filament\Resources\InvestmentCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentCategories extends ListRecords
{
    protected static string $resource = InvestmentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
