<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;



Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:update-rates')->daily();
Schedule::command('app:update-stock-prices')->dailyAt('01:00');
Schedule::command('app:process-recurring-transactions')->dailyAt('06:00');
Schedule::command('investments:execute-plans')->dailyAt('07:00');
Schedule::command('app:take-portfolio-snapshot')->dailyAt('23:55');
