<?php

class MockContribution {
    public $scheme_id;
    public $amount;
    public function __construct($id, $amount) {
        $this->scheme_id = $id;
        $this->amount = $amount;
    }
}

class MockScheme {
    public $id;
    public $name;
    public function __construct($id, $name) {
        $this->id = $id;
        $this->name = $name;
    }
}

$schemes = [
    new MockScheme(1, 'Shares'),
    new MockScheme(2, 'Savings'),
];

$contributions = [
    new MockContribution("1", 1000), // scheme_id as string
    new MockContribution(2, 2000),   // scheme_id as int
];

foreach ($schemes as $scheme) {
    $total = 0;
    foreach ($contributions as $con) {
        if ($con->scheme_id === $scheme->id) {
            $total += $con->amount;
        }
    }
    echo "Scheme: {$scheme->name}, Total (===): {$total}\n";
}

foreach ($schemes as $scheme) {
    $total = 0;
    foreach ($contributions as $con) {
        if ($con->scheme_id == $scheme->id) {
            $total += $con->amount;
        }
    }
    echo "Scheme: {$scheme->name}, Total (==): {$total}\n";
}
