<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Contribution;

$pending = Contribution::where('status', 'pending')->count();
$success = Contribution::where('status', 'success')->count();
$successWithLedger = Contribution::where('status', 'success')->whereNotNull('ledger_journal_id')->count();
$successWithoutLedger = Contribution::where('status', 'success')->whereNull('ledger_journal_id')->count();

echo "Pending: $pending\n";
echo "Success: $success\n";
echo "Success with Ledger: $successWithLedger\n";
echo "Success without Ledger: $successWithoutLedger\n";

$sample = Contribution::where('status', 'success')->whereNull('ledger_journal_id')->limit(5)->get();
foreach ($sample as $s) {
    echo "ID: {$s->id}, Ref: {$s->reference}, Category: {$s->category}, Created: {$s->created_at}\n";
}
