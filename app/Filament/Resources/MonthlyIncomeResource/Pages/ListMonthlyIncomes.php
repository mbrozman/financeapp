<?php

namespace App\Filament\Resources\MonthlyIncomeResource\Pages;

use App\Filament\Resources\MonthlyIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMonthlyIncomes extends ListRecords
{
    protected static string $resource = MonthlyIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
