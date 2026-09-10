<?php
require __DIR__.'/backend/vendor/autoload.php';
$app = require_once __DIR__.'/backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$n = DB::table('notifications')->first();
if ($n) {
    echo "ID: " . $n->id . "\n";
    echo "Type: " . gettype($n->id) . "\n";
    echo "Full record: " . json_encode($n) . "\n";
} else {
    echo "No notifications found.\n";
}
