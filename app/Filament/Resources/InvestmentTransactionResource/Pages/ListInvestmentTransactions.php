<?php

namespace App\Filament\Resources\InvestmentTransactionResource\Pages;

use App\Filament\Resources\InvestmentTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvestmentTransactions extends ListRecords
{
    protected static string $resource = InvestmentTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
