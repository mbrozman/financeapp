<?php

namespace App\Observers;

use App\Models\InvestmentTransaction;
use App\Models\Investment;
use Illuminate\Support\Facades\DB;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class InvestmentTransactionObserver
{
    public function saved(InvestmentTransaction $tx): void
    {
        $investment = $tx->investment;
        $brokerAccount = $investment->account;

        if (!$brokerAccount) return;

        $oldTypeRaw = $tx->getOriginal('type');
        $oldType = ($oldTypeRaw instanceof \App\Enums\TransactionType)
            ? $oldTypeRaw
            : ($oldTypeRaw ? \App\Enums\TransactionType::tryFrom($oldTypeRaw) : null);
        $isNew = !$oldType;

        // Zostávajúca časť logiky synchronizácie zostatku na účte
        if ($tx->subtract_from_broker) {
            // 1. REVERZIA STARÉHO STAVU (ak išlo o editáciu)
            if (!$isNew && $oldType) {
                $oldQty = BigDecimal::of($tx->getOriginal('quantity') ?? 0);
                $oldPrice = BigDecimal::of($tx->getOriginal('price_per_unit') ?? 0);
                $oldComm = BigDecimal::of($tx->getOriginal('commission') ?? 0);

                // Výpočet pôvodnej sumy v mene transakcie: (Ks * Cena) +/- Poplatok
                $oldAmountBase = $oldQty->multipliedBy($oldPrice);
                $oldAmountBase = ($oldType === \App\Enums\TransactionType::BUY)
                    ? $oldAmountBase->plus($oldComm)
                    : $oldAmountBase->minus($oldComm);

                // Prepočet do meny ÚČTU (nie nutne EUR)
                $oldAmountAccountCurrency = CurrencyService::convert(
                    (string) $oldAmountBase,
                    $tx->getOriginal('currency_id'),
                    $brokerAccount->currency_id,
                    $tx->getOriginal('exchange_rate')
                );

                // Vrátime peniaze na účet (opačná operácia)
                if ($oldType === \App\Enums\TransactionType::BUY) {
                    $brokerAccount->increment('balance', (string) $oldAmountAccountCurrency);
                } else {
                    $brokerAccount->decrement('balance', (string) $oldAmountAccountCurrency);
                }
            }

            // 2. APLIKÁCIA NOVÉHO STAVU
            $newQty = BigDecimal::of($tx->quantity);
            $newPrice = BigDecimal::of($tx->price_per_unit);
            $newComm = BigDecimal::of($tx->commission ?? 0);

            $newAmountBase = $newQty->multipliedBy($newPrice);
            $newAmountBase = ($tx->type === \App\Enums\TransactionType::BUY)
                ? $newAmountBase->plus($newComm)
                : $newAmountBase->minus($newComm);

            // Prepočet do meny ÚČTU
            $newAmountAccountCurrency = CurrencyService::convert(
                (string) $newAmountBase,
                $tx->currency_id,
                $brokerAccount->currency_id,
                $tx->exchange_rate
            );

            // Zapíšeme aktuálnu sumu na účet
            if ($tx->type === \App\Enums\TransactionType::BUY) {
                $brokerAccount->decrement('balance', (string) $newAmountAccountCurrency);
            } else {
                $brokerAccount->increment('balance', (string) $newAmountAccountCurrency);
            }
        }

        // AKTUALIZÁCIA ŠTATISTÍK (FIFO persistence) - Toto sa deje VŽDY
        \App\Services\InvestmentCalculationService::refreshStats($investment);
        
        $this->syncInvestmentStatus($investment);
    }

    public function deleted(InvestmentTransaction $tx): void
    {
        $investment = $tx->investment;
        $brokerAccount = $investment->account;

        if (!$brokerAccount) return;

        // Pri zmazaní vraciame stav len vtedy, ak sa pôvodne odpočítalo
        if ($tx->subtract_from_broker) {
            $qty = BigDecimal::of($tx->quantity);
            $price = BigDecimal::of($tx->price_per_unit);
            $comm = BigDecimal::of($tx->commission ?? 0);

            $amountBase = $qty->multipliedBy($price);
            $amountBase = ($tx->type === \App\Enums\TransactionType::BUY)
                ? $amountBase->plus($comm)
                : $amountBase->minus($comm);

            // Prepočet do meny ÚČTU
            $amountAccountCurrency = CurrencyService::convert(
                (string) $amountBase,
                $tx->currency_id,
                $brokerAccount->currency_id,
                $tx->exchange_rate
            );

            // PRI ZMAZANÍ: Vraciame stav účtu späť
            if ($tx->type === \App\Enums\TransactionType::BUY) {
                $brokerAccount->increment('balance', (string) $amountAccountCurrency);
            } else {
                $brokerAccount->decrement('balance', (string) $amountAccountCurrency);
            }
        }

        // AKTUALIZÁCIA ŠTATISTÍK (FIFO persistence)
        \App\Services\InvestmentCalculationService::refreshStats($investment);

        $this->syncInvestmentStatus($investment);
    }


    public function creating(InvestmentTransaction $tx): void
    {
        if ($tx->type === \App\Enums\TransactionType::SELL) {
            $currentQty = (float) $tx->investment->total_quantity;

            if ((float)$tx->quantity > $currentQty) {
                // Zastavíme proces a vyhodíme chybu
                throw new \Exception("Nedostatok kusov na predaj. Máte {$currentQty}, pokúšate sa predať {$tx->quantity}.");
            }
        }
    }


    protected function syncInvestmentStatus(Investment $investment): void
    {
        // Spočítame kusy priamo v DB
        $totalQtyRaw = InvestmentTransaction::where('investment_id', $investment->id)
            ->sum(DB::raw("CASE WHEN type = 'buy' THEN quantity ELSE -quantity END"));

        // Použijeme BigDecimal na porovnanie (kvôli mikroskopickým rozdielom v DB)
        $totalQty = BigDecimal::of($totalQtyRaw ?? 0);

        $investment->updateQuietly([
            'is_archived' => $totalQty->isLessThanOrEqualTo('0.00000001')
        ]);

        // Vymažeme cache, aby sa pri ďalšom prístupe k atribútom (napr. total_quantity) 
        // prepočítali čerstvé dáta.
        $investment->clearStatsCache();
    }
}
