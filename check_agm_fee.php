<?php
require 'backend/vendor/autoload.php';
$app = require_once 'backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schemes = \App\Models\Scheme::all();
foreach ($schemes as $scheme) {
    echo "ID: {$scheme->id}, Name: {$scheme->name}, Min Amount: {$scheme->min_amount}\n";
}

$settings = \App\Models\Setting::all();
foreach ($settings as $setting) {
    echo "Key: {$setting->key}, Value: {$setting->value}\n";
}
