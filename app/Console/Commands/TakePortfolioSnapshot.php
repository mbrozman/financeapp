<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Investment;
use App\Models\PortfolioSnapshot;
use Illuminate\Console\Command;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class TakePortfolioSnapshot extends Command
{
    protected $signature = 'app:take-portfolio-snapshot';
    protected $description = 'Uloží dennú snímku hodnoty portfólia pre všetkých užívateľov';

    public function handle()
{
    User::all()->each(function (User $user) {
        $investments = Investment::where('user_id', $user->id)->where('is_archived', false)->get();

        $totalInvested = BigDecimal::of(0);
        $totalMarket = BigDecimal::of(0);

        foreach ($investments as $investment) {
            $totalInvested = $totalInvested->plus($investment->total_invested_eur);
            $totalMarket = $totalMarket->plus($investment->current_market_value_eur);
        }

        PortfolioSnapshot::updateOrCreate(
            ['user_id' => $user->id, 'recorded_at' => now()->toDateString()],
            [
                'total_invested_eur' => (string) $totalInvested,
                'total_market_value_eur' => (string) $totalMarket,
            ]
        );
    });
}
}