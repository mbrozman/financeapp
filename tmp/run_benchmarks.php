<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

echo "Spúšťam sťahovanie 5-ročnej histórie benchmarkov...\n";
$kernel->call('app:init-benchmarks');
echo "Hotovo!\n";
