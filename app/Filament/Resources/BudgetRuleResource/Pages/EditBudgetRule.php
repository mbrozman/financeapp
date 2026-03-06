<?php

namespace App\Filament\Resources\BudgetRuleResource\Pages;

use App\Filament\Resources\BudgetRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBudgetRule extends EditRecord
{
    protected static string $resource = BudgetRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
