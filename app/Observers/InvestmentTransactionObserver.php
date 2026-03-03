<?php

namespace App\Observers;

use App\Models\InvestmentTransaction;

class InvestmentTransactionObserver
{
    /**
     * Handle the InvestmentTransaction "created" event.
     */
    public function created(InvestmentTransaction $investmentTransaction): void
    {
        //
    }

    /**
     * Handle the InvestmentTransaction "updated" event.
     */
    public function updated(InvestmentTransaction $investmentTransaction): void
    {
        //
    }

    /**
     * Handle the InvestmentTransaction "deleted" event.
     */
    public function deleted(InvestmentTransaction $investmentTransaction): void
    {
        //
    }

    /**
     * Handle the InvestmentTransaction "restored" event.
     */
    public function restored(InvestmentTransaction $investmentTransaction): void
    {
        //
    }

    /**
     * Handle the InvestmentTransaction "force deleted" event.
     */
    public function forceDeleted(InvestmentTransaction $investmentTransaction): void
    {
        //
    }
}
