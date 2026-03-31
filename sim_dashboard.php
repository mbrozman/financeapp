<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\InvestmentPerformanceService;

$service = new InvestmentPerformanceService();
$user = \App\Models\User::first();

$output = "SIMULÁCIA VÝPOČTOV DASHBOARDU\n";
$output .= "=============================\n\n";

if ($user) {
    $data = $service->getComparisonData($user->id);
    
    foreach ($data as $row) {
        $output .= "RIADOK: {$row['label']} ({$row['ticker']})\n";
        foreach ($row['data'] as $period => $value) {
            $valStr = ($value === null) ? 'N/A' : number_format($value, 2) . '%';
            $output .= "  {$period}: {$valStr}\n";
        }
        $output .= "-----------------------------\n";
    }
} else {
    $output .= "Používateľ nenájdený.\n";
}

file_put_contents('diag_results.txt', $output);
echo "Simulácia hotová v diag_results.txt\n";
