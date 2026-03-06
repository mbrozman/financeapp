<?php

namespace App\Filament\Resources\MonthlyIncomeResource\Pages;

use App\Filament\Resources\MonthlyIncomeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMonthlyIncome extends EditRecord
{
    protected static string $resource = MonthlyIncomeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
