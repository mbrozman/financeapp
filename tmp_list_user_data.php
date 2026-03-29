<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$u = '019d07df-2ae4-7080-98af-0b4e8f061599';
$investments = \App\Models\Investment::where('user_id', $u)->get(['id', 'ticker']);
$accounts = \App\Models\Account::where('user_id', $u)->get(['id', 'name']);

echo "INVESTMENTS:\n";
foreach($investments as $i) echo $i->id . " | " . $i->ticker . "\n";

echo "\nACCOUNTS:\n";
foreach($accounts as $a) echo $a->id . " | " . $a->name . "\n";
