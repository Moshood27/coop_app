<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    echo "Running query that failed in the issue description...\n";
    $count = User::query()
        ->where('autosave_enabled', true)
        ->whereNotNull('paystack_authorization_code')
        ->whereNotNull('email')
        ->count();
    
    echo "Found $count users eligible for autosave.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
