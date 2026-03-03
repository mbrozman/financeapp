<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Models\InvestmentTransaction;
use App\Models\Investment;
use App\Services\StockApiService;
use App\Services\CurrencyService; // PRIDANÉ
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;

class CreateInvestment extends CreateRecord
{
    protected static string $resource = InvestmentResource::class;

    /**
     * LOGIKA PO VYTVORENÍ (Prvý nákup a história)
     */
    protected function afterCreate(): void
    {
        $data = $this->form->getRawState();
        $api = new StockApiService();
        $record = $this->record;

        // Ak užívateľ zadal nákup kusov
        if (isset($data['initial_quantity']) && (float) $data['initial_quantity'] > 0) {

            // ZÍSKAME REÁLNY KURZ CEZ NAŠU SLUŽBU (Už žiadne 1.08)
            try {
                // Skúsime nájsť kód meny (napr. USD) cez priradené ID
                $currencyCode = $record->currency?->code ?? 'USD';
                $currentRate = CurrencyService::getRate($currencyCode);
            } catch (\Exception $e) {
                // Ak kurz v DB nie je, nahlásime to, ale nezhodíme celý proces
                Notification::make()->title('Varovanie: Kurz meny nenájdený. Použitý núdzový kurz 1.0.')->warning()->send();
                $currentRate = 1.0;
            }

            // Vytvoríme prvú transakciu v histórii
            InvestmentTransaction::create([
                'user_id' => auth()->id(),
                'investment_id' => $record->id,
                'type' => 'buy',
                'quantity' => $data['initial_quantity'],
                'price_per_unit' => $data['initial_price'],
                'commission' => $data['initial_commission'] ?? 0,
                'currency_id' => $this->$record->currency_id,
                'exchange_rate' => $currentRate, // Dynamický kurz z DB
                'transaction_date' => $data['transaction_date'] ?? now(),
            ]);

            // Okamžite stiahneme históriu cien pre graf v detaile
            $api->downloadHistory($record, 30);
        }
    }

    /**
     * LOGIKA PRED VYTVORENÍM (Ochrana proti duplicitám)
     */
    protected function beforeCreate(): void
    {
        $data = $this->data;
        $ticker = $data['ticker'];
        $broker = $data['broker'] ?? null;

        $existing = Investment::where('user_id', auth()->id())
            ->where('ticker', $ticker)
            ->where('broker', $broker)
            ->first();

        if ($existing) {
            Notification::make()
                ->title('Pozícia už existuje')
                ->body("Investíciu {$ticker} u brokera " . ($broker ?? 'neuvedeného') . " už máte v portfóliu.")
                ->warning()
                ->send();

            $url = InvestmentResource::getUrl('view', ['record' => $existing]);
            $this->redirect($url);
            $this->halt(); // Zastaví vytváranie duplicity
        }
    }

    /**
     * UX: Presmerovanie do detailu akcie
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}