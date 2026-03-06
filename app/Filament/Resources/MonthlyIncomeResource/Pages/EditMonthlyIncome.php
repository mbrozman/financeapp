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
     protected function getRedirectUrl(): string
    {
        // Vráti nás na hlavnú tabuľku (index) tohto modulu
        return $this->getResource()::getUrl('index');
    }
}
