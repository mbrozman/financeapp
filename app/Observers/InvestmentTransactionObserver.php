<?php

namespace App\Observers;

use App\Models\InvestmentTransaction;
use App\Models\Investment;
use Illuminate\Support\Facades\DB;
use App\Services\CurrencyService;

class InvestmentTransactionObserver
{
    public function saved(InvestmentTransaction $tx): void
    {
        $investment = $tx->investment;
        $brokerAccount = $investment->account;

        $oldType = $tx->getOriginal('type');
        $isNew = !$oldType; // pri vytvorení getOriginal('type') je null

        if (!$isNew) {
            // Vypočítame starú sumu a VRÁTIME jej efekt na balance
            $oldAmountBase = ($tx->getOriginal('quantity') * $tx->getOriginal('price_per_unit'))
                + ($oldType === 'buy' ? $tx->getOriginal('commission') : -$tx->getOriginal('commission'));

            $oldAmountEur = CurrencyService::convertToEur(
                $oldAmountBase,
                $tx->getOriginal('currency_id'),
                $tx->getOriginal('exchange_rate')
            );

            // Reverzujeme starý efekt
            if ($oldType === 'buy') $brokerAccount->increment('balance', $oldAmountEur);
            if ($oldType === 'sell') $brokerAccount->decrement('balance', $oldAmountEur);
        }

        // Aplikujeme nový efekt
        $newAmountBase = ($tx->quantity * $tx->price_per_unit)
            + ($tx->type === 'buy' ? $tx->commission : -$tx->commission);

        $newAmountEur = CurrencyService::convertToEur($newAmountBase, $tx->currency_id, $tx->exchange_rate);

        if ($tx->type === 'buy') $brokerAccount->decrement('balance', $newAmountEur);
        if ($tx->type === 'sell') $brokerAccount->increment('balance', $newAmountEur);

        $this->syncInvestmentStatus($investment);
    }

    public function deleted(InvestmentTransaction $tx): void
    {
        $investment = $tx->investment;
        $brokerAccount = $investment->account;

        $amountBase = ($tx->quantity * $tx->price_per_unit) + ($tx->type === 'buy' ? $tx->commission : -$tx->commission);
        $amountEur = CurrencyService::convertToEur($amountBase, $tx->currency_id, $tx->exchange_rate);

        // PRI ZMAZANÍ: Vraciame stav účtu späť
        if ($tx->type === 'buy') $brokerAccount->increment('balance', $amountEur);
        if ($tx->type === 'sell') $brokerAccount->decrement('balance', $amountEur);

        $this->syncInvestmentStatus($investment);
    }

    protected function syncInvestmentStatus(Investment $investment): void
    {
        // Spočítame kusy priamo v DB, aby sme mali 100% istotu
        $totalQty = InvestmentTransaction::where('investment_id', $investment->id)
            ->sum(DB::raw("CASE WHEN type = 'buy' THEN quantity ELSE -quantity END"));

        $investment->updateQuietly([
            'is_archived' => $totalQty <= 0.00000001
        ]);
    }
}
