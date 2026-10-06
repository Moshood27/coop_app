<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Redis Client: " . config('database.redis.client') . "\n";
    echo "Extension 'redis' loaded: " . (extension_loaded('redis') ? 'yes' : 'no') . "\n";

    $redis = Illuminate\Support\Facades\Redis::connection();
    echo "Connection successful!\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}
