<?php

namespace App\Filament\Resources\BudgetRuleResource\Pages;

use App\Filament\Resources\BudgetRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgetRules extends ListRecords
{
    protected static string $resource = BudgetRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
