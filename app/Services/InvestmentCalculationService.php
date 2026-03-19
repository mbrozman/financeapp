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
        // 1. ZÍSKAME TRANSACKIE (vždy čerstvé z DB a zoradené)
        $transactions = $investment->transactions()
            ->orderBy('transaction_date', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();
            
        $baseCurrencyId = $investment->currency_id;

        // 2. INICIALIZÁCIA PREMENNÝCH
        $remainingLots = []; 
        $realizedGainBase = BigDecimal::of(0);
        $totalInvestedBase = BigDecimal::of(0);
        $totalInvestedEur = BigDecimal::of(0);
        $totalSalesBase = BigDecimal::of(0);
        $totalSalesEur = BigDecimal::of(0);
        $totalDividendsBase = BigDecimal::of(0);
        $totalDividendsEur = BigDecimal::of(0);
        $realizedGainEur = BigDecimal::of(0);

        foreach ($transactions as $tx) {
            $priceInBase = CurrencyService::convert($tx->price_per_unit, $tx->currency_id, $baseCurrencyId, $tx->exchange_rate);
            $commInBase = CurrencyService::convert($tx->commission ?? 0, $tx->currency_id, $baseCurrencyId, $tx->exchange_rate);
            
            $priceInEur = CurrencyService::convertToEur($tx->price_per_unit ?? 0, $tx->currency_id, $tx->exchange_rate);
            $commInEur = CurrencyService::convertToEur($tx->commission ?? 0, $tx->currency_id, $tx->exchange_rate);

            $qty = BigDecimal::of($tx->quantity ?? 0);
            $price = BigDecimal::of($priceInBase ?? 0);
            $comm = BigDecimal::of($commInBase ?? 0);

            // --- LOGIKA NÁKUPU ---
            if ($tx->type === TransactionType::BUY) {
                $costWithComm = $qty->multipliedBy($price)->plus($comm);
                $costWithCommEur = $qty->multipliedBy($priceInEur)->plus($commInEur);
                
                $totalInvestedBase = $totalInvestedBase->plus($costWithComm);
                $totalInvestedEur = $totalInvestedEur->plus($costWithCommEur);

                $remainingLots[] = [
                    'qty' => $qty,
                    'qty_orig' => $qty,
                    'price' => $price,
                    'comm_orig' => $comm,
                    'price_eur' => BigDecimal::of($priceInEur),
                    'comm_eur' => BigDecimal::of($commInEur),
                    'date' => $tx->transaction_date,
                ];
            } 
            // --- LOGIKA PREDAJA ---
            elseif ($tx->type === TransactionType::SELL) {
                $revenueMinusComm = $qty->multipliedBy($price)->minus($comm);
                $revenueMinusCommEur = $qty->multipliedBy($priceInEur)->minus($commInEur);
                
                $totalSalesBase = $totalSalesBase->plus($revenueMinusComm);
                $totalSalesEur = $totalSalesEur->plus($revenueMinusCommEur);

                $sellQty = $qty;
                while ($sellQty->isGreaterThan(0) && !empty($remainingLots)) {
                    $oldestLot = &$remainingLots[0];
                    $take = $sellQty->isLessThanOrEqualTo($oldestLot['qty']) ? $sellQty : $oldestLot['qty'];

                    $lotPurchaseCommPerUnit = $oldestLot['qty_orig'] && $oldestLot['qty_orig']->isGreaterThan(0)
                        ? $oldestLot['comm_orig']->dividedBy($oldestLot['qty_orig'], 8, RoundingMode::HALF_UP)
                        : BigDecimal::zero();
                    
                    $purchasePriceWithComm = $oldestLot['price']->plus($lotPurchaseCommPerUnit);

                    $netSellPricePerUnit = $qty->isGreaterThan(0)
                        ? $qty->multipliedBy($price)->minus($comm)->dividedBy($qty, 8, RoundingMode::HALF_UP)
                        : $price;

                    $lotGain = $netSellPricePerUnit->minus($purchasePriceWithComm)->multipliedBy($take);
                    $realizedGainBase = $realizedGainBase->plus($lotGain);

                    // EUR PREPOČET (Zisk v EUR na tomto lote)
                    // (Suma za ktorú predávam v EUR / Celkové množstvo predaja) * Predávané množstvo z lotu - (Nákupná cena lotu v EUR + poplatok) * Predávané množstvo
                    $sellPricePerUnitEur = $qty->isGreaterThan(0) ? $revenueMinusCommEur->dividedBy($qty, 8, RoundingMode::HALF_UP) : BigDecimal::zero();
                    $buyPricePerUnitEurWithComm = $oldestLot['qty_orig']->isGreaterThan(0) 
                        ? $oldestLot['price_eur']->plus($oldestLot['comm_eur']->dividedBy($oldestLot['qty_orig'], 8, RoundingMode::HALF_UP))
                        : $oldestLot['price_eur'];
                    
                    $lotGainEur = $sellPricePerUnitEur->minus($buyPricePerUnitEurWithComm)->multipliedBy($take);
                    $realizedGainEur = $realizedGainEur->plus($lotGainEur);

                    $oldestLot['qty'] = $oldestLot['qty']->minus($take);
                    $sellQty = $sellQty->minus($take);

                    if ($oldestLot['qty']->isZero()) {
                        array_shift($remainingLots);
                    }
                }
            }
            // --- LOGIKA DIVIDEND ---
            elseif ($tx->type === TransactionType::DIVIDEND) {
                $netDividend = $qty->multipliedBy($price)->minus($comm);
                $totalDividendsBase = $totalDividendsBase->plus($netDividend);
                
                $netDividendEur = $qty->multipliedBy($priceInEur)->minus($commInEur);
                $totalDividendsEur = $totalDividendsEur->plus($netDividendEur);
            }
        }

        // 3. VÝPOČET PRE ZVYŠNÉ KUSY
        $currentQty = BigDecimal::of(0);
        $totalCleanCostOfRemaining = BigDecimal::of(0);
        $totalCleanCostOfRemainingEur = BigDecimal::of(0);
        $totalRemainingComm = BigDecimal::of(0);

        foreach ($remainingLots as $lot) {
            $currentQty = $currentQty->plus($lot['qty']);
            $totalCleanCostOfRemaining = $totalCleanCostOfRemaining->plus($lot['qty']->multipliedBy($lot['price']));
            $totalCleanCostOfRemainingEur = $totalCleanCostOfRemainingEur->plus($lot['qty']->multipliedBy($lot['price_eur']));
            
            $lotCommPerUnit = (isset($lot['qty_orig']) && $lot['qty_orig']->isGreaterThan(0))
                ? $lot['comm_orig']->dividedBy($lot['qty_orig'], 8, RoundingMode::HALF_UP)
                : BigDecimal::zero();
            $totalRemainingComm = $totalRemainingComm->plus($lot['qty']->multipliedBy($lotCommPerUnit));
        }

        $avgPrice = $currentQty->isGreaterThan(0) 
            ? $totalCleanCostOfRemaining->dividedBy($currentQty, 4, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        $avgPriceEur = $currentQty->isGreaterThan(0)
            ? $totalCleanCostOfRemainingEur->dividedBy($currentQty, 4, RoundingMode::HALF_UP)
            : BigDecimal::zero();

        $currentPrice = BigDecimal::of($investment->current_price ?? '0');
        $unrealizedGainBase = $currentQty->multipliedBy($currentPrice)
            ->minus($totalCleanCostOfRemaining)
            ->minus($totalRemainingComm);

        return [
            'current_quantity'      => (string) $currentQty,
            'average_buy_price'     => (string) $avgPrice,
            'average_buy_price_eur' => (string) $avgPriceEur,
            'realized_gain_base'    => (string) $realizedGainBase,
            'unrealized_gain_base'  => (string) $unrealizedGainBase,
            'total_invested_base'   => (string) $totalInvestedBase,
            'total_invested_eur'    => (string) $totalInvestedEur,
            'total_sales_base'      => (string) $totalSalesBase,
            'total_sales_eur'       => (string) $totalSalesEur,
            'total_dividends_base'  => (string) $totalDividendsBase,
            'total_dividends_eur'   => (string) $totalDividendsEur,
            'realized_gain_eur'     => (string) $realizedGainEur,
            'remaining_lots'        => $remainingLots,
        ];
    }

    /**
     * Prepočíta a PLNE uloží štatistiky do databázy (Denormalizácia pre výkon)
     */
    public static function refreshStats(Investment $investment): void
    {
        $stats = self::getStats($investment);

        $investment->update([
            'total_quantity'        => $stats['current_quantity'],
            'average_buy_price'     => $stats['average_buy_price'],
            'average_buy_price_eur' => $stats['average_buy_price_eur'],
            'total_invested_base'   => $stats['total_invested_base'],
            'total_invested_eur'    => $stats['total_invested_eur'],
            'total_sales_base'      => $stats['total_sales_base'],
            'total_sales_eur'       => $stats['total_sales_eur'],
            'total_dividends_base'  => $stats['total_dividends_base'],
            'total_dividends_eur'   => $stats['total_dividends_eur'],
            'realized_gain_base'    => $stats['realized_gain_base'],
            'realized_gain_eur'     => $stats['realized_gain_eur'],
            'is_archived'           => (\Brick\Math\BigDecimal::of($stats['current_quantity'] ?? 0)->isZero()),
        ]);
        
        if (method_exists($investment, 'clearStatsCache')) {
            $investment->clearStatsCache();
        }
    }
}