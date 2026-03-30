<?php

namespace App\Filament\Resources\InvestmentPlanResource\Pages;

use App\Filament\Resources\InvestmentPlanResource;
use App\Models\InvestmentPlan;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\InvestmentPlanResource\Pages\Traits\HasInvestmentPlanSync;

class CreateInvestmentPlan extends CreateRecord
{
    use HasInvestmentPlanSync;

    protected static string $resource = InvestmentPlanResource::class;

    protected function handleRecordCreation(array $data): InvestmentPlan
    {
        $itemsData = $data['items'] ?? [];
        unset($data['items']);

        $dbFields = [
            'user_id', 'name', 'account_id', 'amount', 
            'currency_id', 'frequency', 'next_run_date', 'is_active'
        ];
        $cleanData = array_intersect_key($data, array_flip($dbFields));
        $cleanData['user_id'] = auth()->id();

        $record = InvestmentPlan::create($cleanData);

        if ($record) {
            $this->syncItems($record, $itemsData, $data);
            
            // Počiatočný stav
            if (($data['use_initial_state'] ?? false) && count($itemsData) > 0) {
                $this->createInitialTransaction($record, $itemsData, $data);
            }
        }

        return $record;
    }
}
