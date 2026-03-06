<?php

namespace App\Filament\Resources\FinancialPlanResource\Pages;

use App\Filament\Resources\FinancialPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateFinancialPlan extends CreateRecord
{
    protected static string $resource = FinancialPlanResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
