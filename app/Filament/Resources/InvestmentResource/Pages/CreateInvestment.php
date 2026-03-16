<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\Account;
use App\Services\StockApiService;
use App\Services\CurrencyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Filament\Notifications\Notification;
use Brick\Math\BigDecimal; // PRIDANÉ
use App\Enums\TransactionType;

class CreateInvestment extends CreateRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * 1. KROK: KONTROLA DUPLICITY
     */
    protected function beforeCreate(): void
    {
        $ticker = $this->data['ticker'];
        
        // Získame názov brokera z ID účtu, ak vo formulári chýba (keďže je Hidden)
        $broker = $this->data['broker'] ?? Account::find($this->data['account_id'])?->name;

        $existing = Investment::where('user_id', auth()->id())
            ->where('ticker', $ticker)
            ->where('broker', $broker)
            ->first();

        if ($existing) {
            Notification::make()
                ->title('Pozícia už existuje')
                ->body("Investíciu {$ticker} už máte v systéme (možno v archíve). Presmerovali sme vás na jej detail, kde môžete pridať nákup.")
                ->warning()
                ->send();

            $this->redirect(InvestmentResource::getUrl('view', ['record' => $existing]));
            $this->halt();
        }
    }

    /**
     * 2. KROK: BEZPEČNÉ VYTVORENIE (Atomická operácia)
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data) {
            // Očistíme dáta pre model Investment (neukladáme tam polia z nákupu)
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

            // POUŽIJEME BIGDECIMAL NA KONTROLU MNOŽSTVA 
            $initialQty = BigDecimal::of($data['initial_quantity'] ?? 0);

            if ($initialQty->isGreaterThan(0)) {
                $initialCurrencyId = $data['initial_currency_id'] ?? $record->currency_id;
                $rate = $data['exchange_rate'] ?? CurrencyService::getLiveRateById($initialCurrencyId);

                InvestmentTransaction::create([
                    'user_id' => auth()->id(),
                    'investment_id' => $record->id,
                    'currency_id' => $initialCurrencyId,
                    'type' => TransactionType::BUY,
                    // Posielame dáta ako stringy, o presnosť sa postará DB a BigDecimal v modeli
                    'quantity' => (string) $initialQty,
                    'price_per_unit' => $data['initial_price'],
                    'commission' => $data['initial_commission'] ?? 0,
                    'exchange_rate' => $rate,
                    'transaction_date' => $data['transaction_date'] ?? now(),
                ]);

                // Okamžite stiahneme históriu a osviežime model
                app(StockApiService::class)->downloadHistory($record, 365);
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