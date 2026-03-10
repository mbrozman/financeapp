<?php

namespace App\Filament\Resources\InvestmentTransactionResource\Pages;

use App\Filament\Resources\InvestmentTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvestmentTransaction extends CreateRecord
{
    protected static string $resource = InvestmentTransactionResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
