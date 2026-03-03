<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Investment;
use App\Models\PortfolioSnapshot;
use Illuminate\Console\Command;

class TakePortfolioSnapshot extends Command
{
    protected $signature = 'app:take-portfolio-snapshot';
    protected $description = 'Uloží dennú snímku hodnoty portfólia pre všetkých užívateľov';

    public function handle()
    {
        $this->info('Začínam vytvárať snímky portfólií...');

        // Prejdeme všetkých užívateľov v systéme
        User::all()->each(function (User $user) {
            
            // Sčítame jeho nearchivované investície v EUR
            // Používame naše hotové atribúty z modelu Investment
            $investments = Investment::where('user_id', $user->id)
                ->where('is_archived', false)
                ->get();

            $totalInvested = $investments->sum('total_invested_eur');
            $totalMarketValue = $investments->sum('current_market_value_eur');

            // Uložíme snímku (ak už za dnes existuje, aktualizujeme ju)
            PortfolioSnapshot::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'recorded_at' => now()->toDateString(),
                ],
                [
                    'total_invested_eur' => $totalInvested,
                    'total_market_value_eur' => $totalMarketValue,
                ]
            );

            $this->line("Užívateľ {$user->name}: Hotovo ({$totalMarketValue} €)");
        });

        $this->info('Všetky snímky boli úspešne uložené.');
    }
}