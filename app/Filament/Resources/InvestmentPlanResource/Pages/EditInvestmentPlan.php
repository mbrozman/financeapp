<?php

namespace App\Filament\Resources\InvestmentPlanResource\Pages;

use App\Filament\Resources\InvestmentPlanResource;
use App\Models\InvestmentPlan;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\InvestmentPlanResource\Pages\Traits\HasInvestmentPlanSync;

class EditInvestmentPlan extends EditRecord
{
    use HasInvestmentPlanSync;

    protected static string $resource = InvestmentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): \Illuminate\Contracts\Support\Htmlable|string
    {
        return "Úprava plánu: " . ($this->record->name ?? '');
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        $itemsData = $data['items'] ?? [];
        unset($data['items']);

        // Whitelist polí pre DB, aby sme sa vyhli chybe s neexistujúcimi stĺpcami pre virtuálne polia
        $dbFields = [
            'user_id', 'name', 'account_id', 'amount', 
            'currency_id', 'frequency', 'next_run_date', 'is_active'
        ];
        $cleanData = array_intersect_key($data, array_flip($dbFields));
        
        $record->update($cleanData);

        // Synchronizácia položiek
        if ($record instanceof InvestmentPlan) {
            $this->syncItems($record, $itemsData, $data);
        }

        return $record;
    }
}
