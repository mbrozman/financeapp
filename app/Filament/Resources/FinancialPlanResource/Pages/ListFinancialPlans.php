<?php

namespace App\Filament\Resources\FinancialPlanResource\Pages;

use App\Filament\Resources\FinancialPlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFinancialPlans extends ListRecords
{
    protected static string $resource = FinancialPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
             \Filament\Actions\CreateAction::make()
            ->label('Vytvoriť plán')
            // TENTO RIADOK JE KĽÚČOVÝ:
            // Tlačidlo uvidíš len vtedy, ak ešte nemáš v DB žiadny plán
            ->visible(fn () => \App\Models\FinancialPlan::where('user_id', auth()->id())->count() === 0),
        ];
    }
}
