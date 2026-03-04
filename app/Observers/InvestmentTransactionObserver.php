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

        $oldType = $tx->getOriginal('type');
        $isNew = !$oldType;

        // 1. REVERZIA STARÉHO STAVU (ak išlo o editáciu)
        if (!$isNew) {
            $oldQty = BigDecimal::of($tx->getOriginal('quantity') ?? 0);
            $oldPrice = BigDecimal::of($tx->getOriginal('price_per_unit') ?? 0);
            $oldComm = BigDecimal::of($tx->getOriginal('commission') ?? 0);

            // Výpočet pôvodnej sumy: (Ks * Cena) +/- Poplatok
            $oldAmountBase = $oldQty->multipliedBy($oldPrice);
            $oldAmountBase = ($oldType === 'buy')
                ? $oldAmountBase->plus($oldComm)
                : $oldAmountBase->minus($oldComm);

            $oldAmountEur = CurrencyService::convertToEur(
                (string) $oldAmountBase,
                $tx->getOriginal('currency_id'),
                $tx->getOriginal('exchange_rate')
            );

            // Vrátime peniaze na účet (opačná operácia)
            if ($oldType === 'buy') {
                $brokerAccount->increment('balance', (string) $oldAmountEur);
            } else {
                $brokerAccount->decrement('balance', (string) $oldAmountEur);
            }
        }

        // 2. APLIKÁCIA NOVÉHO STAVU
        $newQty = BigDecimal::of($tx->quantity);
        $newPrice = BigDecimal::of($tx->price_per_unit);
        $newComm = BigDecimal::of($tx->commission ?? 0);

        $newAmountBase = $newQty->multipliedBy($newPrice);
        $newAmountBase = ($tx->type === 'buy')
            ? $newAmountBase->plus($newComm)
            : $newAmountBase->minus($newComm);

        $newAmountEur = CurrencyService::convertToEur(
            (string) $newAmountBase,
            $tx->currency_id,
            $tx->exchange_rate
        );

        // Zapíšeme aktuálnu sumu na účet
        if ($tx->type === 'buy') {
            $brokerAccount->decrement('balance', (string) $newAmountEur);
        } else {
            $brokerAccount->increment('balance', (string) $newAmountEur);
        }

        $this->syncInvestmentStatus($investment);
    }

    public function deleted(InvestmentTransaction $tx): void
    {
        $investment = $tx->investment;
        $brokerAccount = $investment->account;

        $qty = BigDecimal::of($tx->quantity);
        $price = BigDecimal::of($tx->price_per_unit);
        $comm = BigDecimal::of($tx->commission ?? 0);

        $amountBase = $qty->multipliedBy($price);
        $amountBase = ($tx->type === 'buy')
            ? $amountBase->plus($comm)
            : $amountBase->minus($comm);

        $amountEur = CurrencyService::convertToEur(
            (string) $amountBase,
            $tx->currency_id,
            $tx->exchange_rate
        );

        // PRI ZMAZANÍ: Vraciame stav účtu späť
        if ($tx->type === 'buy') {
            $brokerAccount->increment('balance', (string) $amountEur);
        } else {
            $brokerAccount->decrement('balance', (string) $amountEur);
        }

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
            'is_archived' => $totalQty->isLessThanOrEqualTo(0.00000001)
        ]);
    }
}
