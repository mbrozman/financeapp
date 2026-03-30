<?php

namespace App\Filament\Resources\InvestmentPlanResource\Pages;

use App\Filament\Resources\InvestmentPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewInvestmentPlan extends ViewRecord
{
    protected static string $resource = InvestmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return $this->record->name ?? parent::getTitle();
    }
}
