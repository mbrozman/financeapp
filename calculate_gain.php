<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$investments = \App\Models\Investment::where('user_id', 1)->where('is_archived', false)->get();
$totalInvested = $investments->sum('total_invested_eur');
$totalGain = $investments->sum(fn($i) => (float)$i->getGainForCurrency('EUR'));

if ($totalInvested > 0) {
    $percent = ($totalGain / $totalInvested) * 100;
    $output = "CELKOVÁ ANALÝZA PORTFÓLIA:\n";
    $output .= "--------------------------\n";
    $output .= "Investované: " . number_format($totalInvested, 2, ',', ' ') . " EUR\n";
    $output .= "Zisk/Strata: " . number_format($totalGain, 2, ',', ' ') . " EUR\n";
    $output .= "VYNOS PORTFOLIA: " . number_format($percent, 2, ',', ' ') . " %\n";
    file_put_contents('gain_result.txt', $output);
    echo "Výsledok zapísaný do gain_result.txt\n";
} else {
    file_put_contents('gain_result.txt', "Žiadne investície nenájdené.");
}
