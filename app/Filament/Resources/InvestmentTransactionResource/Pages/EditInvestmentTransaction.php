<?php

namespace App\Filament\Resources\InvestmentTransactionResource\Pages;

use App\Filament\Resources\InvestmentTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvestmentTransaction extends EditRecord
{
    protected static string $resource = InvestmentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
