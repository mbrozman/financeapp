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
            // Prepočítame cenu a poplatok do NAtívnej meny aktíva (napr. z EUR do USD) pomocou HISTORICKÉHO kurzu
            $priceInBase = CurrencyService::convert($tx->price_per_unit, $tx->currency_id, $baseCurrencyId, $tx->exchange_rate);
            $commInBase = CurrencyService::convert($tx->commission ?? 0, $tx->currency_id, $baseCurrencyId, $tx->exchange_rate);

            // Taktiež si spočítame priamo hodnoty v EUR pre presnú FIFO nákupku
            $priceInEur = CurrencyService::convertToEur($tx->price_per_unit, $tx->currency_id, $tx->exchange_rate);
            $commInEur = CurrencyService::convertToEur($tx->commission ?? 0, $tx->currency_id, $tx->exchange_rate);

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
                // NÁKLAD NA KUS = (Suma + Poplatok) / Počet kusov (Pre presný zisk)
                $lotPriceWithComm = $qty->isGreaterThan(0) 
                    ? $costWithComm->dividedBy($qty, 8, RoundingMode::HALF_UP)
                    : $price;

                $remainingLots[] = [
                    'qty' => $qty,
                    'qty_orig' => $qty, // Uložíme pôvodný počet pre neskorší prepočet poplatku
                    'price' => $price, // CLEAN market price v BASE
                    'comm' => $comm,   // Celý poplatok za tento lot v BASE
                    'comm_orig' => $comm,
                    'price_eur' => BigDecimal::of($priceInEur), // CLEAN market price v EUR
                    'comm_eur' => BigDecimal::of($commInEur),   // Celý poplatok v EUR
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

                    // Realizovaný zisk = (Čistá predajná cena - Nákupná cena s alikvótnym poplatkom)
                    // Pôvodný poplatok na kus v tomto lot-e
                    $lotPurchaseCommPerUnit = $oldestLot['qty_orig'] && $oldestLot['qty_orig']->isGreaterThan(0)
                        ? $oldestLot['comm_orig']->dividedBy($oldestLot['qty_orig'], 8, RoundingMode::HALF_UP)
                        : BigDecimal::zero();
                    
                    $purchasePriceWithComm = $oldestLot['price']->plus($lotPurchaseCommPerUnit);

                    // Čistá predajná cena na kus pre túto transakciu
                    $netSellPricePerUnit = $qty->isGreaterThan(0)
                        ? $qty->multipliedBy($price)->minus($comm)->dividedBy($qty, 8, RoundingMode::HALF_UP)
                        : $price;

                    $lotGain = $netSellPricePerUnit->minus($purchasePriceWithComm)->multipliedBy($take);
                    $realizedGainBase = $realizedGainBase->plus($lotGain);

                    $oldestLot['qty'] = $oldestLot['qty']->minus($take);
                    $sellQty = $sellQty->minus($take);

                    if ($oldestLot['qty']->isZero()) {
                        array_shift($remainingLots);
                    }
                }
            }
        }

        // 3. VÝPOČET PRE ZVYŠNÉ KUSY (To, čo dnes držíš)
        $currentQty = BigDecimal::of(0);
        $totalCleanCostOfRemaining = BigDecimal::of(0);
        $totalCleanCostOfRemainingEur = BigDecimal::of(0);
        $totalRemainingComm = BigDecimal::of(0);

        foreach ($remainingLots as $lot) {
            $currentQty = $currentQty->plus($lot['qty']);
            $totalCleanCostOfRemaining = $totalCleanCostOfRemaining->plus($lot['qty']->multipliedBy($lot['price']));
            $totalCleanCostOfRemainingEur = $totalCleanCostOfRemainingEur->plus($lot['qty']->multipliedBy($lot['price_eur']));
            
            // Alikvótna časť pôvodného poplatku, ktorá zostáva
            $lotCommPerUnit = (isset($lot['qty_orig']) && $lot['qty_orig']->isGreaterThan(0))
                ? $lot['comm_orig']->dividedBy($lot['qty_orig'], 8, RoundingMode::HALF_UP)
                : BigDecimal::zero();
            $totalRemainingComm = $totalRemainingComm->plus($lot['qty']->multipliedBy($lotCommPerUnit));
        }

        // Priemerná nákupná cena (ČISTÁ - ako chce užívateľ podľa XTB)
        $avgPrice = $currentQty->isGreaterThan(0) 
            ? $totalCleanCostOfRemaining->dividedBy($currentQty, 4, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        $avgPriceEur = $currentQty->isGreaterThan(0)
            ? $totalCleanCostOfRemainingEur->dividedBy($currentQty, 4, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        // Nerealizovaný zisk = (Aktuálna hodnota) - (Čistá nákupná cena + Zostávajúce poplatky)
        $currentPrice = BigDecimal::of($investment->current_price ?? 0);
        $unrealizedGainBase = $currentQty->multipliedBy($currentPrice)
            ->minus($totalCleanCostOfRemaining)
            ->minus($totalRemainingComm);

        // 4. VRACIAME VÝSLEDKY AKO STRINGY
        return [
            'current_quantity' => (string) $currentQty,
            'average_buy_price' => (string) $avgPrice,
            'average_buy_price_eur' => (string) $avgPriceEur,
            'realized_gain_base' => (string) $realizedGainBase,
            'unrealized_gain_base' => (string) $unrealizedGainBase,
            'total_invested_base' => (string) $totalInvestedBase,
            'total_sales_base' => (string) $totalSalesBase,
            'remaining_lots' => $remainingLots,
        ];
    }

    /**
     * Prepočíta a PLNE uloží štatistiky do databázy (Denormalizácia pre výkon)
     */
    public static function refreshStats(Investment $investment): void
    {
        $stats = self::getStats($investment);

        $investment->updateQuietly([
            'total_quantity' => $stats['current_quantity'],
            'average_buy_price' => $stats['average_buy_price'],
            'average_buy_price_eur' => $stats['average_buy_price_eur'],
            'total_invested_base' => $stats['total_invested_base'],
            'total_sales_base' => $stats['total_sales_base'],
            'realized_gain_base' => $stats['realized_gain_base'],
            'is_archived' => (\Brick\Math\BigDecimal::of($stats['current_quantity'] ?? 0)->isZero()),
        ]);
        
        if (method_exists($investment, 'clearStatsCache')) {
            $investment->clearStatsCache();
        }
    }
}