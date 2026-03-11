<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class HideResourcesFromSuperadmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:hide-resources-from-superadmin';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle()
    {
        $models = [
            'Account', 'ActivityLog', 'Broker', 'Budget', 'Category', 'Currency', 
            'FinancialPlan', 'Goal', 'InvestmentCategory', 'Investment', 
            'InvestmentTransaction', 'MonthlyIncome', 'PortfolioSnapshot', 
            'Subcategory', 'Tag', 'Transaction'
        ];

        foreach ($models as $model) {
            $policyPath = app_path('Policies/' . $model . 'Policy.php');
            
            if (!file_exists($policyPath)) {
                $this->info("Creating policy for {$model}");
                \Illuminate\Support\Facades\Artisan::call('make:policy', [
                    'name' => "{$model}Policy",
                    '--model' => $model
                ]);
            }

            $content = file_get_contents($policyPath);
            $content = str_replace('return false;', 'return !auth()->user()->is_superadmin;', $content);
            file_put_contents($policyPath, $content);
            $this->info("Updated policy for {$model}");
        }
    }
}
