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
        // Ak by náhodou broker v dátach chýbal (lebo je Hidden), skúsime ho získať z account_id
        $broker = $this->data['broker'] ?? \App\Models\Account::find($this->data['account_id'])?->name;

        $existing = Investment::where('user_id', auth()->id())
            ->where('ticker', $ticker)
            ->where('broker', $broker)
            ->first();

        if ($existing) {
            Notification::make()
                ->title('Pozícia už existuje')
                ->body("Investíciu {$ticker} u brokera {$broker} už máte v portfóliu.")
                ->warning()
                ->send();

            $this->redirect(InvestmentResource::getUrl('view', ['record' => $existing]));
            $this->halt();
        }
    }

    /**
     * 2. KROK: BEZPEČNÉ VYTVORENIE (Ak prešlo cez kontrolu vyššie)
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Vyčistíme dáta pre Investíciu
            $investmentData = collect($data)->except([
                'initial_quantity',
                'initial_price',
                'initial_commission',
                'transaction_date'
            ])->toArray();

            $investmentData['last_price_update'] = now();

            $record = new Investment();
            $record->fill($investmentData);
            $record->user_id = auth()->id();
            $record->save();

            if (isset($data['initial_quantity']) && (float) $data['initial_quantity'] > 0) {
                $currentRate = \App\Services\CurrencyService::getLiveRate($record->currency?->code);

                \App\Models\InvestmentTransaction::create([
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

                // DÔLEŽITÉ: Stiahneme históriu a refreshneme hneď
                app(\App\Services\StockApiService::class)->downloadHistory($record, 30);
                $record->refresh();
            }

            return $record;
        });
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
