<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Auth\Events\Logout;

$user = User::factory()->create(['last_activity_at' => now()]);
echo 'Initial: ' . ($user->last_activity_at ?: 'NULL') . PHP_EOL;

event(new Logout('web', $user));

$user->refresh();
echo 'After: ' . ($user->last_activity_at ?: 'NULL') . PHP_EOL;

if ($user->last_activity_at === null) {
    echo "SUCCESS\n";
} else {
    echo "FAILURE\n";
}

$user->delete();
