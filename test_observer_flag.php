<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

// Find a user for testing
$user = User::first();
if (!$user) {
    echo "No user found.\n";
    exit;
}

echo "Testing user ID: " . $user->id . "\n";
echo "Initial skip_auto_collection: " . ($user->skip_auto_collection ? 'true' : 'false') . "\n";

$user->skip_auto_collection = true;
echo "Set skip_auto_collection to true.\n";

// We want to see if the observer sees this.
// I'll add a temporary log or something, but since I can't easily do that,
// I'll check if the property persists after increment.
$user->increment('balance', 0.01);

echo "After increment, skip_auto_collection: " . ($user->skip_auto_collection ? 'true' : 'false') . "\n";

// Now, let's see if the UserObserver was triggered.
// I can't see that directly unless I add a log in UserObserver.
