<?php

// Mocking some Laravel classes for testing the logic
class Collection extends ArrayObject {
    public function sum($key) {
        $sum = 0;
        foreach ($this as $item) $sum += $item[$key];
        return $sum;
    }
    public function map($callback) {
        $res = [];
        foreach ($this as $item) $res[] = $callback($item);
        return new self($res);
    }
}

// Simulated data
$activeScheme = (object)['id' => 1, 'name' => 'Active Scheme', 'active' => true];
$inactiveScheme = (object)['id' => 2, 'name' => 'Inactive Scheme', 'active' => false];

$yearContributions = [
    (object)['scheme_id' => "1", 'amount' => 100, 'paid_at' => (object)['month' => 1]], // String ID
    (object)['scheme_id' => 2, 'amount' => 200, 'paid_at' => (object)['month' => 2]],   // Inactive scheme
];

$bfContributions = [
    (object)['scheme_id' => 1, 'amount' => 50],
];

// Logic from PassbookController (updated)
$userSchemeIds = [1, 2]; // Simulated pluck
$schemes = new Collection([$activeScheme, $inactiveScheme]);

$matrix = $schemes->map(function ($scheme) use ($yearContributions, $bfContributions) {
    $row = [
        'scheme_name' => $scheme->name,
        'months' => array_fill(1, 12, 0),
        'bf' => 0.0,
        'total' => 0.0,
    ];

    foreach ($bfContributions as $con) {
        // Updated to ==
        if ($con->scheme_id == $scheme->id) {
            $row['bf'] += (float) $con->amount;
        }
    }

    foreach ($yearContributions as $con) {
        // Updated to ==
        if ($con->scheme_id == $scheme->id) {
            $date = $con->paid_at;
            $month = $date->month;
            $row['months'][$month] += (float) $con->amount;
            $row['total'] += (float) $con->amount;
        }
    }

    return $row;
});

echo "Matrix Result:\n";
foreach ($matrix as $row) {
    echo "Scheme: {$row['scheme_name']}, BF: {$row['bf']}, Total: {$row['total']}\n";
}

$grandTotal = $matrix->sum('total');
$bfTotal = $matrix->sum('bf');

echo "Grand Total: {$grandTotal}\n";
echo "BF Total: {$bfTotal}\n";

if ($grandTotal == 300 && $bfTotal == 50) {
    echo "VERIFICATION SUCCESSFUL\n";
} else {
    echo "VERIFICATION FAILED\n";
}
