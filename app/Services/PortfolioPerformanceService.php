<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Models\PortfolioSnapshot;
use App\Enums\TransactionType;
use Carbon\Carbon;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

class PortfolioPerformanceService
{
    /**
     * Calculates the Money-Weighted Return (MWR) using the XIRR approach.
     * XIRR is the discount rate that makes the NPV of all cash flows equal to zero.
     */
    public static function calculateMWR($userId, string $targetCurrency = 'EUR'): float
    {
        $transactions = InvestmentTransaction::where('user_id', $userId)
            ->with(['investment.currency'])
            ->get();

        $cashFlows = [];

        // 1. External Cash Flows (Investments are negative cash flows for the investor's pocket)
        foreach ($transactions as $tx) {
            $amountBase = (string) BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->plus($tx->commission ?? 0);
            $amountEur = CurrencyService::convertToEur($amountBase, $tx->currency_id, $tx->exchange_rate);
            
            // For MWR, a BUY is money leaving the "wallet" (negative)
            // A SELL is money entering the "wallet" (positive)
            $flow = ($tx->type === TransactionType::BUY) ? -(float)$amountEur : (float)$amountEur;
            
            $cashFlows[] = [
                'date' => $tx->transaction_date,
                'amount' => $flow
            ];
        }

        // 2. Add current portfolio value as a final positive cash flow (terminal value)
        $investments = Investment::where('user_id', $userId)->where('is_archived', false)->get();
        $currentValueEur = 0;
        foreach ($investments as $inv) {
            $currentValueEur += (float) CurrencyService::convertToEur($inv->current_market_value_base, $inv->currency_id);
        }

        if (count($cashFlows) === 0) return 0;

        $cashFlows[] = [
            'date' => now(),
            'amount' => $currentValueEur
        ];

        return self::xirr($cashFlows) * 100;
    }

    /**
     * Calculates Time-Weighted Return (TWR).
     * TWR = [(1 + r1) * (1 + r2) * ... * (1 + rn)] - 1
     * where rn is the return for each sub-period divided by a cash flow.
     */
    public static function calculateTWR($userId): float
    {
        // For a true TWR, we need historical snapshots exactly when transactions happened.
        // If we don't have perfect snapshots, we approximate using periodic ones.
        
        $snapshots = PortfolioSnapshot::where('user_id', $userId)
            ->orderBy('recorded_at')
            ->get();

        if ($snapshots->count() < 2) return 0;

        $totalTWR = 1.0;
        
        for ($i = 1; $i < $snapshots->count(); $i++) {
            $prev = $snapshots[$i - 1];
            $curr = $snapshots[$i];

            $beginningValue = (float)$prev->total_market_value_eur;
            $endingValue = (float)$curr->total_market_value_eur;
            
            // We need to account for external cash flows during THIS specific period
            $netCashFlow = InvestmentTransaction::where('user_id', $userId)
                ->whereBetween('transaction_date', [$prev->recorded_at, $curr->recorded_at])
                ->get()
                ->sum(function($tx) {
                    $amountBase = (string) BigDecimal::of($tx->quantity)->multipliedBy($tx->price_per_unit)->plus($tx->commission ?? 0);
                    $amountEur = CurrencyService::convertToEur($amountBase, $tx->currency_id, $tx->exchange_rate);
                    return ($tx->type === TransactionType::BUY) ? (float)$amountEur : -(float)$amountEur;
                });

            // Period Return = (EndValue - NetCashFlow - StartValue) / StartValue
            if ($beginningValue > 0) {
                $periodReturn = ($endingValue - $netCashFlow - $beginningValue) / $beginningValue;
                $totalTWR *= (1 + $periodReturn);
            }
        }

        return ($totalTWR - 1) * 100;
    }

    /**
     * Newton-Raphson method to find XIRR
     */
    private static function xirr(array $cashFlows): float
    {
        $rate = 0.1; // Initial guess 10%
        for ($i = 0; $i < 100; $i++) {
            $npv = 0;
            $dnpv = 0;
            foreach ($cashFlows as $flow) {
                $days = $flow['date']->diffInDays($cashFlows[0]['date']) / 365.25;
                $npv += $flow['amount'] / pow(1 + $rate, $days);
                $dnpv -= $days * $flow['amount'] / pow(1 + $rate, $days + 1);
            }
            if (abs($dnpv) < 0.0000001) break;
            $newRate = $rate - $npv / $dnpv;
            if (abs($newRate - $rate) < 0.0000001) break;
            $rate = $newRate;
        }
        return $rate;
    }
}
