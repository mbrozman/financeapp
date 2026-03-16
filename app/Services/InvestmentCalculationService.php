<?php

namespace App\Services;

use App\Models\Investment;
use App\Enums\TransactionType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

use App\Services\CurrencyService;

class InvestmentCalculationService
{
    /**
     * Vypočíta kompletné štatistiky investície pomocou metódy FIFO.
     */
    public static function getStats(Investment $investment): array
    {
        // 1. ZÍSKAME TRANSACKIE (zoradené podľa dátumu)
        $transactions = $investment->transactions->sortBy('transaction_date');
        $baseCurrencyId = $investment->currency_id;

        // 2. INICIALIZÁCIA PREMENNÝCH (BigDecimal pre presnosť)
        $remainingLots = []; 
        $realizedGainBase = BigDecimal::of(0);
        $totalInvestedBase = BigDecimal::of(0); // Pridané
        $totalSalesBase = BigDecimal::of(0);    // Pridané
        $totalCommissionBase = BigDecimal::of(0);

        foreach ($transactions as $tx) {
            // Prepočítame cenu a poplatok do NAtívnej meny aktíva (napr. z EUR do USD)
            $priceInBase = CurrencyService::convert($tx->price_per_unit, $tx->currency_id, $baseCurrencyId);
            $commInBase = CurrencyService::convert($tx->commission ?? 0, $tx->currency_id, $baseCurrencyId);

            $qty = BigDecimal::of($tx->quantity);
            $price = BigDecimal::of($priceInBase);
            $comm = BigDecimal::of($commInBase);
            
            $totalCommissionBase = $totalCommissionBase->plus($comm);

            // --- LOGIKA NÁKUPU ---
            if ($tx->type === TransactionType::BUY) {
                // Sčítame celkovú investíciu (Nákupná cena + Poplatky)
                $costWithComm = $qty->multipliedBy($price)->plus($comm);
                $totalInvestedBase = $totalInvestedBase->plus($costWithComm);

                // Pridáme nákupný balík do "skladu" pre FIFO
                $remainingLots[] = [
                    'qty' => $qty,
                    'price' => $price,
                    'date' => $tx->transaction_date,
                ];
            } 
            
            // --- LOGIKA PREDAJA ---
            elseif ($tx->type === TransactionType::SELL) {
                // Sčítame celkové tržby (Predajná cena - Poplatky)
                $revenueMinusComm = $qty->multipliedBy($price)->minus($comm);
                $totalSalesBase = $totalSalesBase->plus($revenueMinusComm);

                $sellQty = $qty;

                // Odpíšeme kusy zo skladu podľa FIFO
                while ($sellQty->isGreaterThan(0) && !empty($remainingLots)) {
                    $oldestLot = &$remainingLots[0];
                    
                    $take = $sellQty->isLessThanOrEqualTo($oldestLot['qty']) ? $sellQty : $oldestLot['qty'];

                    // Realizovaný zisk = (Predajná cena - Pôvodná nákupná cena balíka) * počet kusov
                    $lotGain = $price->minus($oldestLot['price'])->multipliedBy($take);
                    $realizedGainBase = $realizedGainBase->plus($lotGain);

                    $oldestLot['qty'] = $oldestLot['qty']->minus($take);
                    $sellQty = $sellQty->minus($take);

                    if ($oldestLot['qty']->isZero()) {
                        array_shift($remainingLots);
                    }
                }
                // Od celkového realizovaného zisku odpočítame poplatok za tento konkrétny predaj
                $realizedGainBase = $realizedGainBase->minus($comm);
            }
        }

        // 3. VÝPOČET PRE ZVYŠNÉ KUSY (To, čo dnes držíš)
        $currentQty = BigDecimal::of(0);
        $totalCostOfRemaining = BigDecimal::of(0);

        foreach ($remainingLots as $lot) {
            $currentQty = $currentQty->plus($lot['qty']);
            $totalCostOfRemaining = $totalCostOfRemaining->plus($lot['qty']->multipliedBy($lot['price']));
        }

        // Priemerná nákupná cena aktuálne držaných kusov
        $avgPrice = $currentQty->isGreaterThan(0) 
            ? $totalCostOfRemaining->dividedBy($currentQty, 4, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        // 4. VRACIAME VÝSLEDKY AKO STRINGY
        return [
            'current_quantity' => (string) $currentQty,
            'average_buy_price' => (string) $avgPrice,
            'realized_gain_base' => (string) $realizedGainBase,
            'total_invested_base' => (string) $totalInvestedBase,
            'total_sales_base' => (string) $totalSalesBase,
            'remaining_lots' => $remainingLots,
        ];
    }
}