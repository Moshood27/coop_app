<?php

use App\Models\Setting;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

function debug() {
    echo "Debugging Settings...\n";

    Setting::set('test_key', '0');
    $val = Setting::get('test_key');
    echo "test_key set to '0', get returns: ";
    var_dump($val);

    Setting::set('test_key_bool', false);
    $val2 = Setting::get('test_key_bool');
    echo "test_key_bool set to false, get returns: ";
    var_dump($val2);

    Setting::set('auto_fine_deduction_enabled', '0');
    $val3 = Setting::get('auto_fine_deduction_enabled', true);
    echo "auto_fine_deduction_enabled set to '0', get returns: ";
    var_dump($val3);
}

debug();
