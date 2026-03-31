<?php

namespace App\Providers;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
        URL::forceScheme('https');
    }
        \App\Models\InvestmentTransaction::observe(\App\Observers\InvestmentTransactionObserver::class);
        \App\Models\InvestmentDividend::observe(\App\Observers\InvestmentDividendObserver::class);
        \App\Models\User::observe(\App\Observers\UserObserver::class);
    }
}
