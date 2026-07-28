<?php

use App\Models\Contribution;
use Illuminate\Support\Facades\DB;

require __DIR__.'/backend/vendor/autoload.php';
$app = require_once __DIR__.'/backend/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$reference = 'WALLET_TOPUP_20260724060750_2120_571828';

echo "Searching for reference: $reference\n";

$contributions = Contribution::where('reference', $reference)->get();

if ($contributions->isEmpty()) {
    echo "No contributions found for this reference.\n";
} else {
    echo "Found " . $contributions->count() . " contributions:\n";
    foreach ($contributions as $c) {
        echo "- ID: {$c->id}, User ID: {$c->user_id}, Status: {$c->status}, Amount: {$c->amount}\n";
    }
}

$webhookCalls = DB::table('webhook_calls')->where('payload', 'like', "%$reference%")->get();
if ($webhookCalls->isEmpty()) {
    echo "No webhook_calls found containing this reference.\n";
} else {
    echo "Found " . $webhookCalls->count() . " webhook_calls.\n";
    foreach ($webhookCalls as $wc) {
        echo "- ID: {$wc->id}, Name: {$wc->name}, Created At: {$wc->created_at}\n";
    }
}
