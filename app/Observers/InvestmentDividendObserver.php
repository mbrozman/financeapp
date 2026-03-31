<?php

namespace App\Observers;

use App\Models\InvestmentDividend;
use App\Services\CurrencyService;
use Brick\Math\BigDecimal;

class InvestmentDividendObserver
{
    public function saved(InvestmentDividend $dividend): void
    {
        $investment = $dividend->investment;
        $brokerAccount = $investment->account;

        if (!$brokerAccount) return;

        $isNew = $dividend->wasRecentlyCreated;

        if ($dividend->add_to_broker_balance) {
            // 1. REVERZIA STARÉHO STAVU (ak ide o editáciu)
            if (!$isNew) {
                $oldAddToBroker = $dividend->getOriginal('add_to_broker_balance');
                
                if ($oldAddToBroker) {
                    $oldAmountBase = BigDecimal::of($dividend->getOriginal('amount') ?? 0);
                    
                    $oldAmountAccountCurrency = CurrencyService::convert(
                        (string) $oldAmountBase,
                        $dividend->getOriginal('currency_id'),
                        $brokerAccount->currency_id,
                        $dividend->getOriginal('exchange_rate')
                    );

                    // Revert starého pridania (odčítame predchádzajúcu dividendu z konta)
                    $brokerAccount->decrement('balance', (string) $oldAmountAccountCurrency);
                }
            }

            // 2. APLIKÁCIA NOVÉHO STAVU
            $newAmountBase = BigDecimal::of($dividend->amount);
            
            $newAmountAccountCurrency = CurrencyService::convert(
                (string) $newAmountBase,
                $dividend->currency_id,
                $brokerAccount->currency_id,
                $dividend->exchange_rate
            );

            // Nabíjanie konta novou dividendou
            $brokerAccount->increment('balance', (string) $newAmountAccountCurrency);
        } elseif (!$isNew && $dividend->getOriginal('add_to_broker_balance')) {
            // Ak editujeme dividendu a UŽÍVATEĽ RUKOU ZRUŠIL zaškrtávátko "add_to_broker"
            // musíme starú dividendu reverznúť.
            $oldAmountBase = BigDecimal::of($dividend->getOriginal('amount') ?? 0);
                    
            $oldAmountAccountCurrency = CurrencyService::convert(
                (string) $oldAmountBase,
                $dividend->getOriginal('currency_id'),
                $brokerAccount->currency_id,
                $dividend->getOriginal('exchange_rate')
            );
            $brokerAccount->decrement('balance', (string) $oldAmountAccountCurrency);
        }

        // AKTUALIZÁCIA ŠTATISTÍK (Total ROI)
        \App\Services\InvestmentCalculationService::refreshStats($investment);
    }

    public function deleted(InvestmentDividend $dividend): void
    {
        $investment = $dividend->investment;
        $brokerAccount = $investment->account;

        if (!$brokerAccount) return;

        if ($dividend->add_to_broker_balance) {
            $amountBase = BigDecimal::of($dividend->amount);
            
            $amountAccountCurrency = CurrencyService::convert(
                (string) $amountBase,
                $dividend->currency_id,
                $brokerAccount->currency_id,
                $dividend->exchange_rate
            );

            // Zmazali sme dividendu, stiahneme cash
            $brokerAccount->decrement('balance', (string) $amountAccountCurrency);
        }

        \App\Services\InvestmentCalculationService::refreshStats($investment);
    }
}
