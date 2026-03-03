<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Services\StockApiService;
use App\Services\CurrencyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateInvestment extends CreateRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * TÁTO METÓDA NAHRÁDZA afterCreate() PRE VYŠŠIU BEZPEČNOSŤ
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Spustíme DB transakciu
        return DB::transaction(function () use ($data) {
            // 1. Vytvoríme hlavný záznam investície
            $record = new Investment();
            $record->fill($data);
            $record->user_id = auth()->id();
            $record->save();

            // 2. Ak užívateľ zadal prvý nákup, vytvoríme transakciu
            if (isset($data['initial_quantity']) && (float) $data['initial_quantity'] > 0) {
                
                $currentRate = CurrencyService::getLiveRate($record->currency?->code ?? 'USD');

                InvestmentTransaction::create([
                    'user_id' => auth()->id(),
                    'investment_id' => $record->id,
                    'type' => 'buy',
                    'quantity' => $data['initial_quantity'],
                    'price_per_unit' => $data['initial_price'],
                    'commission' => $data['initial_commission'] ?? 0,
                    'currency_id' => $record->currency_id,
                    'exchange_rate' => $currentRate,
                    'transaction_date' => $data['transaction_date'] ?? now(),
                ]);

                // 3. Stiahneme históriu cien (toto môže bežať aj mimo transakcie, ale tu je to istejšie)
                app(StockApiService::class)->downloadHistory($record, 30);
            }

            return $record;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}