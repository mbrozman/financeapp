<?php

namespace App\Observers;

use App\Models\InvestmentTransaction;
use App\Models\Investment;
use Illuminate\Support\Facades\DB;
use App\Services\CurrencyService;

class InvestmentTransactionObserver
{
    /**
     * Spustí sa po VYTVORENÍ alebo UPRAVENÍ transakcie
     */
    public function saved(InvestmentTransaction $tx): void
    {
        $this->syncEverything($tx, 'process');
    }

    /**
     * Spustí sa po VYMAZANÍ transakcie
     * (TOTO JE TÁ OCHRANA, KTORÁ CHÝBALA)
     */
    public function deleted(InvestmentTransaction $tx): void
    {
        $this->syncEverything($tx, 'reverse');
    }

    /**
     * Jednotná metóda na synchronizáciu peňazí a stavu
     */
    protected function syncEverything(InvestmentTransaction $tx, string $mode): void
    {
        $investment = $tx->investment;
        $brokerAccount = $investment->account;

        // 1. Vypočítame sumu v EUR, ktorú transakcia predstavovala
        $amountBase = ($tx->quantity * $tx->price_per_unit) + ($tx->type === 'buy' ? $tx->commission : -$tx->commission);
        $amountEur = CurrencyService::convertToEur($amountBase, $tx->currency_id, $tx->exchange_rate);

        // 2. AKTUALIZÁCIA HOTOVOSTI U BROKERA
        if ($mode === 'process') {
            // Bežný proces: nákup peniaze berie, predaj dáva
            if ($tx->type === 'buy') $brokerAccount->decrement('balance', $amountEur);
            if ($tx->type === 'sell') $brokerAccount->increment('balance', $amountEur);
        } else {
            // REVERZNÝ PROCES (pri mazaní): Ak mažem nákup, peniaze vraciam na účet
            if ($tx->type === 'buy') $brokerAccount->increment('balance', $amountEur);
            if ($tx->type === 'sell') $brokerAccount->decrement('balance', $amountEur);
        }

        // 3. PREPOČET ARCHIVÁCIE
        // Spočítame reálny zostatok kusov priamo v DB po zmene
        $totalQty = InvestmentTransaction::where('investment_id', $investment->id)
            ->sum(DB::raw("CASE WHEN type = 'buy' THEN quantity ELSE -quantity END"));

        $investment->updateQuietly([
            'is_archived' => $totalQty <= 0.00000001
        ]);
    }
}