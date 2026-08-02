<?php

require __DIR__ . '/backend/vendor/autoload.php';
$app = require_once __DIR__ . '/backend/bootstrap/app.php';

use App\Models\Contribution;
use App\Models\User;
use App\Models\Scheme;
use Illuminate\Support\Facades\DB;

$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$stats = [
    'total_success' => Contribution::where('status', 'success')->count(),
    'success_with_active_scheme' => Contribution::where('status', 'success')
        ->whereHas('scheme', fn($q) => $q->where('active', true))
        ->count(),
    'success_with_inactive_scheme' => Contribution::where('status', 'success')
        ->whereHas('scheme', fn($q) => $q->where('active', false))
        ->count(),
    'success_without_scheme' => Contribution::where('status', 'success')
        ->whereNull('scheme_id')
        ->count(),
    'success_with_null_paid_at' => Contribution::where('status', 'success')
        ->whereNull('paid_at')
        ->count(),
];

echo "Contribution Stats:\n";
print_r($stats);

$inactiveSchemes = Scheme::where('active', false)->get(['id', 'name']);
echo "\nInactive Schemes:\n";
foreach ($inactiveSchemes as $s) {
    $count = Contribution::where('scheme_id', $s->id)->where('status', 'success')->count();
    echo "- {$s->name} (ID: {$s->id}): {$count} successful contributions\n";
}

// Check some recent successful contributions
echo "\nRecent 10 Successful Contributions:\n";
$recent = Contribution::with('scheme')->where('status', 'success')->latest()->take(10)->get();
foreach ($recent as $c) {
    echo "ID: {$c->id}, User: {$c->user_id}, Scheme: " . ($c->scheme->name ?? 'N/A') . " (Active: " . ($c->scheme->active ?? 'N/A') . "), Amount: {$c->amount}, Paid At: {$c->paid_at}, Category: {$c->category}\n";
}
