<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Investment;
use App\Services\InvestmentCalculationService;

class RefreshInvestments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'investments:refresh';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepočíta štatistiky (FIFO, EUR) pre všetky investície';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $investments = Investment::all();
        $count = $investments->count();

        $this->info("Začínam prepočet $count investícií...");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($investments as $investment) {
            InvestmentCalculationService::refreshStats($investment);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Všetky investície boli úspešne prepočítané.');
    }
}
