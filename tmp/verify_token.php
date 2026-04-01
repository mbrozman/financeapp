<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokenFromEnv = config('app.cron_token');
$expectedToken = '2aa887a7ae16adf33398050c742d22df';

echo "Config Token: $tokenFromEnv\n";
echo "Expected Token: $expectedToken\n";

if ($tokenFromEnv === $expectedToken) {
    echo "✅ Success: Tokens match!\n";
} else {
    echo "❌ Failure: Tokens do NOT match!\n";
}
