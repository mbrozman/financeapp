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
use Filament\Notifications\Notification;

class CreateInvestment extends CreateRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * 1. KROK: KONTROLA DUPLICITY (Ešte pred vytvorením)
     */
    protected function beforeCreate(): void
    {
        $ticker = $this->data['ticker'];
        $broker = $this->data['broker'] ?? null;

        $existing = Investment::where('user_id', auth()->id())
            ->where('ticker', $ticker)
            ->where('broker', $broker)
            ->first();

        if ($existing) {
            // Pošleme notifikáciu
            Notification::make()
                ->title('Pozícia už existuje')
                ->body("Ticker {$ticker} u brokera {$broker} už máte v portfóliu. Presmerovali sme vás na detail.")
                ->warning()
                ->send();

            // Okamžité presmerovanie
            $url = InvestmentResource::getUrl('view', ['record' => $existing]);
            $this->redirect($url);

            // ZASTAVÍME VŠETKO (tým pádom sa handleRecordCreation ani nespustí)
            $this->halt();
        }
    }

    /**
     * 2. KROK: BEZPEČNÉ VYTVORENIE (Ak prešlo cez kontrolu vyššie)
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Očistíme dáta pre model Investment
            $investmentData = collect($data)->except([
                'initial_quantity', 
                'initial_price', 
                'initial_commission', 
                'transaction_date'
            ])->toArray();

            $record = new Investment();
            $record->fill($investmentData);
            $record->user_id = auth()->id();
            $record->save();

            if (isset($data['initial_quantity']) && (float) $data['initial_quantity'] > 0) {
                
                $currentRate = CurrencyService::getLiveRate($record->currency?->code ?? 'USD');

                InvestmentTransaction::create([
                    'user_id' => auth()->id(),
                    'investment_id' => $record->id,
                    'currency_id' => $record->currency_id,
                    'type' => 'buy',
                    'quantity' => $data['initial_quantity'],
                    'price_per_unit' => $data['initial_price'],
                    'commission' => $data['initial_commission'] ?? 0,
                    'exchange_rate' => $currentRate,
                    'transaction_date' => $data['transaction_date'] ?? now(),
                ]);

                // Kľúčové pre zobrazenie dát hneď po presmerovaní
                $record->refresh();
                
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