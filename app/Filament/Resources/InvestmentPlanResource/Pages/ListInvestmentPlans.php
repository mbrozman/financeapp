<?php

namespace App\Filament\Resources\InvestmentPlanResource\Pages;

use App\Filament\Resources\InvestmentPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentPlans extends ListRecords
{
    protected static string $resource = InvestmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
