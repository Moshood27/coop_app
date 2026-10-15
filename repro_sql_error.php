<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "Current DIR: " . __DIR__ . "\n";
echo "Working DIR: " . getcwd() . "\n";

$autoload = file_exists(__DIR__ . '/vendor/autoload.php') 
    ? __DIR__ . '/vendor/autoload.php' 
    : (file_exists(__DIR__ . '/backend/vendor/autoload.php') ? __DIR__ . '/backend/vendor/autoload.php' : null);

if (!$autoload) {
    die("Could not find vendor/autoload.php\n");
}

require $autoload;
$app_path = dirname($autoload) . '/bootstrap/app.php';
$app = require_once $app_path;
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Using User model from: " . (new ReflectionClass(User::class))->getFileName() . "\n";
    echo "Running fixed query...\n";
    $count = User::query()
        ->with('virtualAccount')
        ->where('autosave_enabled', true)
        ->whereHas('virtualAccount', function($q) {
            $q->whereNotNull('paystack_authorization_code');
        })
        ->whereNotNull('email')
        ->count();
    
    echo "Found $count users eligible for autosave.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
